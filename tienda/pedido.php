<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
require_once dirname(__DIR__) . '/includes/clientes.php';
$usuario = requiereRol(ROL_CLIENTE);

$pedidoId = entradaEntero('id');
$pedido = obtenerUno(
    'SELECT p.*, e.nombre AS estatus, m.ultimos_digitos, m.titular, t.nombre AS tipo_tarjeta,
            d.calle, d.numero_exterior, d.numero_interior, d.colonia, d.codigo_postal, d.municipio, d.ciudad, pa.nombre AS pais
       FROM pedidos p
       JOIN estatus_pedido e ON e.id = p.estatus_id
       JOIN metodos_pago m ON m.id = p.metodo_pago_id
       JOIN tipos_tarjeta t ON t.id = m.tipo_tarjeta_id
       JOIN direcciones d ON d.id = p.direccion_id
       JOIN paises pa ON pa.id = d.pais_id
      WHERE p.id = ? AND p.usuario_id = ?',
    [$pedidoId, $usuario['id']]
);
if ($pedido === null) {
    mensajeFlash('error', 'El pedido solicitado no existe.');
    redirigir('/tienda/pedidos.php');
}

$detalle = obtenerTodos(
    'SELECT d.producto_id, d.cantidad, d.precio_unitario, pr.nombre,
            (SELECT i.ruta FROM producto_imagenes i WHERE i.producto_id = pr.id ORDER BY i.orden, i.id LIMIT 1) AS imagen
       FROM pedido_detalle d JOIN productos pr ON pr.id = d.producto_id
      WHERE d.pedido_id = ?',
    [$pedidoId]
);
$historial = obtenerTodos(
    'SELECT h.fecha, e.nombre AS estatus FROM pedido_historial h JOIN estatus_pedido e ON e.id = h.estatus_id
      WHERE h.pedido_id = ? ORDER BY h.fecha, h.id',
    [$pedidoId]
);
$estatusCatalogo = obtenerTodos('SELECT id, nombre, orden FROM estatus_pedido ORDER BY orden');
$ordenActual = (int) obtenerValor('SELECT orden FROM estatus_pedido WHERE id = ?', [$pedido['estatus_id']]);

$esConfirmacionNueva = ($_SESSION['pedido_confirmado'] ?? 0) === $pedidoId;
unset($_SESSION['pedido_confirmado']);

$tituloPagina = 'Pedido n.º ' . $pedidoId;
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('tienda/cuenta.php')) ?>">Mi cuenta</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('tienda/pedidos.php')) ?>">Mis pedidos</a></li>
        <li class="breadcrumb-item active">Pedido n.º <?= (int) $pedidoId ?></li>
    </ol>
</nav>

<?php if ($esConfirmacionNueva): ?>
    <div class="alert alert-success">
        <h4 class="alert-heading"><i class="bi bi-check-circle"></i> ¡Gracias por su compra!</h4>
        <p>Su pago fue aprobado. Enviamos la confirmación a <strong><?= e($usuario['email']) ?></strong>.</p>
        <hr>
        <p class="mb-0"><strong>Notificación de envío:</strong> su pedido n.º <?= (int) $pedidoId ?> se está preparando y será enviado a
            <?= e($pedido['calle'] . ' ' . $pedido['numero_exterior'] . ', ' . $pedido['ciudad']) ?>.
            Entrega estimada: <strong><?= e(formatoFecha($pedido['fecha_entrega_estimada'])) ?></strong>.</p>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h4 mb-0">Pedido n.º <?= (int) $pedidoId ?></h1>
            <span class="badge fs-6 <?= claseEstatus((int) $pedido['estatus_id']) ?>"><?= e($pedido['estatus']) ?></span>
        </div>
        <p class="text-muted">Realizado el <?= e(formatoFecha($pedido['fecha'], true)) ?></p>

        <div class="d-flex linea-tiempo my-4" aria-label="Estatus del pedido">
            <?php foreach ($estatusCatalogo as $estatus): ?>
                <div class="paso <?= (int) $estatus['orden'] <= $ordenActual ? 'completo' : '' ?>"><?= e($estatus['nombre']) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <div class="col-md-3">
                <h6>Dirección de envío</h6>
                <p class="small"><?= e($usuario['nombres'] . ' ' . $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno']) ?><br><?= e(direccionEnTexto($pedido)) ?></p>
            </div>
            <div class="col-md-3">
                <h6>Método de pago</h6>
                <p class="small"><?= e($pedido['tipo_tarjeta']) ?> terminación <?= e($pedido['ultimos_digitos']) ?><br><?= e($pedido['titular']) ?></p>
            </div>
            <div class="col-md-3">
                <h6>Entrega estimada</h6>
                <p class="small"><?= e(formatoFecha($pedido['fecha_entrega_estimada'])) ?></p>
            </div>
            <div class="col-md-3">
                <h6>Historial</h6>
                <ul class="small ps-3">
                    <?php foreach ($historial as $evento): ?>
                        <li><?= e($evento['estatus']) ?> <span class="text-muted">(<?= e(formatoFecha($evento['fecha'], true)) ?>)</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Productos</strong></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th></th><th>Producto</th><th>Cantidad</th><th>Precio unitario</th><th class="text-end">Importe</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($detalle as $renglon): ?>
                <tr>
                    <td><img class="miniatura rounded" src="<?= e(rutaImagen($renglon['imagen'])) ?>" alt=""></td>
                    <td><a href="<?= e(url('tienda/producto.php?id=' . (int) $renglon['producto_id'])) ?>"><?= e($renglon['nombre']) ?></a></td>
                    <td><?= (int) $renglon['cantidad'] ?></td>
                    <td><?= e(formatoMoneda($renglon['precio_unitario'])) ?></td>
                    <td class="text-end"><?= e(formatoMoneda($renglon['precio_unitario'] * $renglon['cantidad'])) ?></td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('tienda/producto.php?id=' . (int) $renglon['producto_id'] . '#opiniones')) ?>">Calificar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="4">Total</td><td class="text-end"><?= e(formatoMoneda($pedido['total'])) ?></td><td></td></tr></tfoot>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
