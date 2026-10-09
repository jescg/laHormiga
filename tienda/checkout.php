<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
require_once dirname(__DIR__) . '/includes/clientes.php';
$usuario = requiereRol(ROL_CLIENTE);
$usuarioId = (int) $usuario['id'];

const ESTATUS_PAGADA = 2;

$direcciones = direccionesDelUsuario($usuarioId);
$tarjetas = tarjetasDelUsuario($usuarioId);
$articulos = obtenerTodos(
    'SELECT ci.producto_id, ci.cantidad, p.nombre, p.precio, p.dias_entrega,
            (SELECT i.ruta FROM producto_imagenes i WHERE i.producto_id = p.id ORDER BY i.orden, i.id LIMIT 1) AS imagen
       FROM carrito_items ci JOIN productos p ON p.id = ci.producto_id
      WHERE ci.usuario_id = ? AND p.activo = 1
      ORDER BY ci.agregado_en DESC',
    [$usuarioId]
);
if (!$articulos) {
    mensajeFlash('aviso', 'Su carrito está vacío.');
    redirigir('/tienda/carrito.php');
}

$errores = [];
$direccionSeleccionada = entradaEntero('direccion_id', (int) ($direcciones[0]['id'] ?? 0));
$tarjetaPredeterminada = 0;
foreach ($tarjetas as $tarjeta) {
    if (!tarjetaVencida($tarjeta)) {
        $tarjetaPredeterminada = (int) $tarjeta['id'];
        break;
    }
}
$tarjetaSeleccionada = entradaEntero('metodo_pago_id', $tarjetaPredeterminada);

if (esPost()) {
    verificarCsrf();
    $direccionValida = obtenerUno('SELECT id FROM direcciones WHERE id = ? AND usuario_id = ? AND activo = 1', [$direccionSeleccionada, $usuarioId]);
    $tarjetaValida = obtenerUno('SELECT id, mes_vencimiento, anio_vencimiento, ultimos_digitos FROM metodos_pago WHERE id = ? AND usuario_id = ? AND activo = 1', [$tarjetaSeleccionada, $usuarioId]);

    if ($direccionValida === null) {
        $errores[] = 'Seleccione una dirección de envío válida.';
    }
    if ($tarjetaValida === null) {
        $errores[] = 'Seleccione un método de pago válido.';
    } elseif (tarjetaVencida($tarjetaValida)) {
        $errores[] = 'La tarjeta seleccionada está vencida. Elija otra.';
    }
    if (!validarCvv(entrada('cvv'))) {
        $errores[] = 'Escriba el CVV de la tarjeta (3 o 4 dígitos) para confirmar el pago.';
    }

    if (!$errores) {
        $conexion = conexion();
        try {
            $conexion->beginTransaction();

            // Se bloquean los productos para validar existencias sin condiciones de carrera
            $productosBloqueados = obtenerTodos(
                'SELECT p.id, p.nombre, p.precio, p.stock, p.dias_entrega, ci.cantidad
                   FROM carrito_items ci JOIN productos p ON p.id = ci.producto_id
                  WHERE ci.usuario_id = ? AND p.activo = 1
                  FOR UPDATE',
                [$usuarioId]
            );
            $total = 0;
            $diasEntregaMaximos = 1;
            foreach ($productosBloqueados as $productoBloqueado) {
                if ((int) $productoBloqueado['cantidad'] > (int) $productoBloqueado['stock']) {
                    $errores[] = 'No se cuenta con la cantidad de unidades del producto «' . $productoBloqueado['nombre'] . '» para cumplir con el pedido.';
                }
                $total += $productoBloqueado['precio'] * $productoBloqueado['cantidad'];
                $diasEntregaMaximos = max($diasEntregaMaximos, (int) $productoBloqueado['dias_entrega']);
            }
            if (!$productosBloqueados) {
                $errores[] = 'Su carrito está vacío.';
            }

            if ($errores) {
                $conexion->rollBack();
            } else {
                $fechaEntrega = date('Y-m-d', strtotime('+' . $diasEntregaMaximos . ' days'));
                // Pago simulado: se aprueba y el pedido queda como «Pagada»
                consulta(
                    'INSERT INTO pedidos (usuario_id, direccion_id, metodo_pago_id, estatus_id, total, fecha_entrega_estimada) VALUES (?, ?, ?, ?, ?, ?)',
                    [$usuarioId, $direccionSeleccionada, $tarjetaSeleccionada, ESTATUS_PAGADA, $total, $fechaEntrega]
                );
                $pedidoId = (int) $conexion->lastInsertId();
                foreach ($productosBloqueados as $productoBloqueado) {
                    consulta(
                        'INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)',
                        [$pedidoId, $productoBloqueado['id'], $productoBloqueado['cantidad'], $productoBloqueado['precio']]
                    );
                    consulta('UPDATE productos SET stock = stock - ? WHERE id = ?', [$productoBloqueado['cantidad'], $productoBloqueado['id']]);
                }
                consulta('INSERT INTO pedido_historial (pedido_id, estatus_id, usuario_id) VALUES (?, 1, ?), (?, ?, ?)', [
                    $pedidoId, $usuarioId, $pedidoId, ESTATUS_PAGADA, $usuarioId,
                ]);
                consulta('DELETE FROM carrito_items WHERE usuario_id = ?', [$usuarioId]);
                $conexion->commit();

                enviarCorreo(
                    $usuario['email'],
                    'Confirmación de su pedido #' . $pedidoId . ' en La Hormiga',
                    "Hola, {$usuario['nombres']}:\n\nRecibimos el pago de su pedido #{$pedidoId} por " . formatoMoneda($total)
                    . ".\nFecha de entrega estimada: " . formatoFecha($fechaEntrega)
                    . ".\n\nLe avisaremos cuando su pedido sea enviado.\n\nLa Hormiga"
                );
                $_SESSION['pedido_confirmado'] = $pedidoId;
                redirigir('/tienda/pedido.php?id=' . $pedidoId);
            }
        } catch (PDOException $excepcion) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            error_log('Error en checkout: ' . $excepcion->getMessage());
            $errores[] = 'No fue posible procesar su pago. Inténtelo de nuevo.';
        }
    }
}

$subtotal = 0;
$diasEntregaMaximos = 1;
foreach ($articulos as $articulo) {
    $subtotal += $articulo['precio'] * $articulo['cantidad'];
    $diasEntregaMaximos = max($diasEntregaMaximos, (int) $articulo['dias_entrega']);
}

$tituloPagina = 'Finalizar compra';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<h1 class="h3 mb-3">Finalizar compra</h1>
<?php if ($errores): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0"><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        <?php if (strpos(implode(' ', $errores), 'No se cuenta') !== false): ?>
            <p class="mt-2 mb-0"><a class="alert-link" href="<?= e(url('tienda/carrito.php')) ?>">Modifique las cantidades en su carrito</a> para continuar.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" data-validar data-confirmar="¿Confirma el pago de su pedido por <?= e(formatoMoneda($subtotal)) ?>?">
    <?= campoCsrf() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <strong>1. Dirección de envío</strong>
                    <a href="<?= e(url('tienda/direcciones.php?accion=nueva&volver=checkout')) ?>">Agregar dirección</a>
                </div>
                <div class="card-body campo">
                    <?php if (!$direcciones): ?><p class="text-danger mb-0">No tiene direcciones registradas.</p><?php endif; ?>
                    <?php foreach ($direcciones as $direccion): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="direccion_id" id="direccion<?= (int) $direccion['id'] ?>" value="<?= (int) $direccion['id'] ?>" <?= $direccionSeleccionada === (int) $direccion['id'] ? 'checked' : '' ?> data-regla="requerido">
                            <label class="form-check-label" for="direccion<?= (int) $direccion['id'] ?>">
                                <?= e(direccionEnTexto($direccion)) ?>
                                <?php if ($direccion['es_principal']): ?><span class="badge text-bg-success">Principal</span><?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <strong>2. Método de pago</strong>
                    <a href="<?= e(url('tienda/metodos_pago.php?accion=nueva&volver=checkout')) ?>">Agregar tarjeta</a>
                </div>
                <div class="card-body">
                    <div class="campo">
                        <?php if (!$tarjetas): ?><p class="text-danger mb-0">No tiene tarjetas registradas.</p><?php endif; ?>
                        <?php foreach ($tarjetas as $tarjeta): $vencida = tarjetaVencida($tarjeta); ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="metodo_pago_id" id="tarjeta<?= (int) $tarjeta['id'] ?>" value="<?= (int) $tarjeta['id'] ?>" <?= $tarjetaSeleccionada === (int) $tarjeta['id'] ? 'checked' : '' ?> <?= $vencida ? 'disabled' : '' ?> data-regla="requerido">
                                <label class="form-check-label" for="tarjeta<?= (int) $tarjeta['id'] ?>">
                                    <?= e($tarjeta['tipo']) ?> terminación <?= e($tarjeta['ultimos_digitos']) ?>
                                    <small class="text-muted">(<?= e($tarjeta['titular']) ?>, vence <?= str_pad((string) $tarjeta['mes_vencimiento'], 2, '0', STR_PAD_LEFT) ?>/<?= (int) $tarjeta['anio_vencimiento'] ?>)</small>
                                    <?php if ($tarjeta['es_principal']): ?><span class="badge text-bg-success">Principal</span><?php endif; ?>
                                    <?php if ($vencida): ?><span class="badge text-bg-danger">Vencida</span><?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="campo mt-3" style="max-width:220px">
                        <label class="form-label" for="cvv">CVV de la tarjeta</label>
                        <input class="form-control" type="password" id="cvv" name="cvv" maxlength="4" inputmode="numeric" required data-regla="cvv" data-solo-digitos autocomplete="cc-csc">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>3. Productos</strong></div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($articulos as $articulo): ?>
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <img class="miniatura rounded" src="<?= e(rutaImagen($articulo['imagen'])) ?>" alt="">
                            <div class="flex-grow-1"><?= e($articulo['nombre']) ?><br><small class="text-muted">Cantidad: <?= (int) $articulo['cantidad'] ?></small></div>
                            <span><?= e(formatoMoneda($articulo['precio'] * $articulo['cantidad'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Resumen del pedido</h2>
                    <table class="table table-sm">
                        <tr><td>Productos</td><td class="text-end"><?= e(formatoMoneda($subtotal)) ?></td></tr>
                        <tr><td>Envío</td><td class="text-end">Gratis</td></tr>
                        <tr class="fw-bold"><td>Total</td><td class="text-end"><?= e(formatoMoneda($subtotal)) ?></td></tr>
                    </table>
                    <p class="small"><i class="bi bi-calendar-check"></i> Entrega estimada: <strong><?= e(formatoFecha(date('Y-m-d', strtotime('+' . $diasEntregaMaximos . ' days')))) ?></strong></p>
                    <button type="submit" class="btn btn-hormiga w-100" <?= (!$direcciones || !$tarjetas) ? 'disabled' : '' ?>>Confirmar y pagar</button>
                    <p class="small text-muted mt-2 mb-0">Pago simulado con fines académicos.</p>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
