<?php
require_once dirname(__DIR__) . '/includes/inicio.php';

$productoId = entradaEntero('id');
$producto = obtenerUno(SQL_PRODUCTOS_TIENDA . ' AND p.id = ?', [$productoId]);
if ($producto === null) {
    http_response_code(404);
    $tituloPagina = 'Producto no encontrado';
    require dirname(__DIR__) . '/includes/encabezado.php';
    echo '<div class="text-center py-5"><h1 class="h3">Producto no disponible</h1><p class="text-muted">El producto que busca no existe o ya no está a la venta.</p>'
        . '<a class="btn btn-primary" href="' . e(url('tienda/catalogo.php')) . '">Volver al catálogo</a></div>';
    require dirname(__DIR__) . '/includes/pie.php';
    exit;
}
$descripcion = obtenerValor('SELECT descripcion FROM productos WHERE id = ?', [$productoId]);

$usuarioSesion = usuarioActual();
$esCliente = $usuarioSesion !== null && $usuarioSesion['rol'] === ROL_CLIENTE;
$puedeOpinar = $esCliente && clienteComproProducto((int) $usuarioSesion['id'], $productoId);

// Calificar y recomendar (solo clientes que compraron el producto)
if (esPost()) {
    verificarCsrf();
    if (!$puedeOpinar) {
        mensajeFlash('error', 'Solo puede calificar o recomendar productos que haya comprado.');
        redirigir('/tienda/producto.php?id=' . $productoId);
    }
    $accion = entrada('accion');
    if ($accion === 'calificar') {
        $estrellas = entrada('estrellas');
        if (!validarEnteroRango($estrellas, 0, 5)) {
            mensajeFlash('error', 'Seleccione una calificación de 0 a 5 estrellas.');
        } else {
            consulta(
                'INSERT INTO calificaciones (producto_id, usuario_id, estrellas) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE estrellas = VALUES(estrellas), fecha = CURRENT_TIMESTAMP',
                [$productoId, $usuarioSesion['id'], (int) $estrellas]
            );
            mensajeFlash('exito', 'Gracias. Su calificación se guardó correctamente.');
        }
    } elseif ($accion === 'recomendar') {
        $comentario = entrada('comentario');
        if (mb_strlen($comentario, 'UTF-8') < 10 || mb_strlen($comentario, 'UTF-8') > 1000) {
            mensajeFlash('error', 'La recomendación debe tener entre 10 y 1000 caracteres.');
        } else {
            consulta('INSERT INTO recomendaciones (producto_id, usuario_id, comentario) VALUES (?, ?, ?)', [$productoId, $usuarioSesion['id'], $comentario]);
            mensajeFlash('exito', 'Gracias. Su recomendación se publicó correctamente.');
        }
    }
    redirigir('/tienda/producto.php?id=' . $productoId . '#opiniones');
}

$imagenes = obtenerTodos('SELECT ruta FROM producto_imagenes WHERE producto_id = ? ORDER BY orden, id', [$productoId]);
$distribucion = array_fill(0, 6, 0);
foreach (obtenerTodos('SELECT estrellas, COUNT(*) AS total FROM calificaciones WHERE producto_id = ? GROUP BY estrellas', [$productoId]) as $fila) {
    $distribucion[(int) $fila['estrellas']] = (int) $fila['total'];
}
$recomendaciones = obtenerTodos(
    'SELECT r.comentario, r.fecha, u.nombres, u.apellido_paterno, ca.estrellas
       FROM recomendaciones r
       JOIN usuarios u ON u.id = r.usuario_id
       LEFT JOIN calificaciones ca ON ca.producto_id = r.producto_id AND ca.usuario_id = r.usuario_id
      WHERE r.producto_id = ?
      ORDER BY r.fecha DESC',
    [$productoId]
);
$miCalificacion = $esCliente
    ? obtenerValor('SELECT estrellas FROM calificaciones WHERE producto_id = ? AND usuario_id = ?', [$productoId, $usuarioSesion['id']])
    : null;
$relacionados = obtenerTodos(SQL_PRODUCTOS_TIENDA . ' AND p.categoria_id = ? AND p.id <> ? ORDER BY RAND() LIMIT 4', [$producto['categoria_id'], $productoId]);
$totalCalificaciones = (int) $producto['total_calificaciones'];

$tituloPagina = $producto['nombre'];
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('tienda/catalogo.php')) ?>">Catálogo</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('tienda/catalogo.php?categoria=' . (int) $producto['categoria_id'])) ?>"><?= e($producto['categoria']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e(mb_strimwidth($producto['nombre'], 0, 50, '…', 'UTF-8')) ?></li>
    </ol>
</nav>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <img id="imagenPrincipal" class="img-fluid w-100 rounded imagen-principal" src="<?= e(rutaImagen($imagenes[0]['ruta'] ?? null)) ?>" alt="<?= e($producto['nombre']) ?>">
                <?php if (count($imagenes) > 1): ?>
                    <div class="d-flex flex-wrap gap-2 mt-2 miniaturas-galeria">
                        <?php foreach ($imagenes as $indice => $imagen): ?>
                            <img class="rounded border <?= $indice === 0 ? 'activa' : '' ?>" src="<?= e(rutaImagen($imagen['ruta'])) ?>" data-imagen-galeria="<?= e(rutaImagen($imagen['ruta'])) ?>" alt="Imagen <?= $indice + 1 ?>">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <span class="badge text-bg-secondary mb-2"><?= e($producto['categoria']) ?></span>
                <h1 class="h3"><?= e($producto['nombre']) ?></h1>
                <p><?= estrellasHtml((float) $producto['promedio']) ?> <span class="text-muted"><?= number_format((float) $producto['promedio'], 1) ?> (<?= $totalCalificaciones ?> <?= $totalCalificaciones === 1 ? 'calificación' : 'calificaciones' ?>)</span></p>
                <p class="mb-1"><?= precioHtml($producto['precio'], 'fs-3') ?></p>
                <p class="text-muted small">Precio con IVA incluido.</p>
                <p><i class="bi bi-truck"></i> <strong>Entrega estimada:</strong> <?= e(textoEntrega((int) $producto['dias_entrega'])) ?></p>
                <h5>Descripción</h5>
                <p><?= nl2br(e($descripcion)) ?></p>

                <?php if ($usuarioSesion === null || $esCliente): ?>
                    <form method="post" action="<?= e(url('api/carrito.php')) ?>" class="row g-2 align-items-end" data-agregar-carrito data-validar>
                        <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                        <div class="col-4 col-lg-3 campo">
                            <label class="form-label" for="cantidad">Cantidad</label>
                            <select class="form-select" id="cantidad" name="cantidad" data-regla="entero:1:99">
                                <?php for ($cantidad = 1; $cantidad <= 10; $cantidad++): ?>
                                    <option value="<?= $cantidad ?>"><?= $cantidad ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col">
                            <button type="submit" class="btn btn-hormiga w-100"><i class="bi bi-cart-plus"></i> Agregar al carrito</button>
                        </div>
                    </form>
                    <a class="btn btn-outline-secondary w-100 mt-2" href="<?= e(url('tienda/carrito.php')) ?>">Ir al carrito</a>
                <?php else: ?>
                    <div class="alert alert-secondary">Las compras están disponibles solo para cuentas de cliente.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4" id="opiniones">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5">Calificaciones</h2>
                <p><?= estrellasHtml((float) $producto['promedio']) ?> <strong><?= number_format((float) $producto['promedio'], 1) ?> de 5</strong></p>
                <?php for ($estrellas = 5; $estrellas >= 0; $estrellas--):
                    $porcentaje = $totalCalificaciones > 0 ? round($distribucion[$estrellas] * 100 / $totalCalificaciones) : 0; ?>
                    <div class="d-flex align-items-center gap-2 small mb-1">
                        <span style="width:70px"><?= $estrellas ?> <?= $estrellas === 1 ? 'estrella' : 'estrellas' ?></span>
                        <div class="progress flex-grow-1" role="progressbar" aria-valuenow="<?= (int) $porcentaje ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-warning" style="width:<?= (int) $porcentaje ?>%"></div>
                        </div>
                        <span style="width:36px"><?= (int) $porcentaje ?>%</span>
                    </div>
                <?php endfor; ?>

                <hr>
                <?php if ($puedeOpinar): ?>
                    <h3 class="h6">Califique este producto</h3>
                    <form method="post" data-validar data-confirmar="¿Desea guardar su calificación?">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="calificar">
                        <div class="campo mb-2">
                            <div class="selector-estrellas d-flex align-items-center flex-wrap">
                                <input type="hidden" name="estrellas" value="<?= e($miCalificacion ?? '') ?>" data-regla="estrellas">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-valor="0" style="font-size:.8rem">0</button>
                                <?php for ($estrella = 1; $estrella <= 5; $estrella++): ?>
                                    <button type="button" class="btn btn-link p-0" data-valor="<?= $estrella ?>" aria-label="<?= $estrella ?> estrellas">&#9733;</button>
                                <?php endfor; ?>
                                <small class="valor-estrellas text-muted ms-2"></small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary"><?= $miCalificacion !== null ? 'Actualizar calificación' : 'Enviar calificación' ?></button>
                    </form>
                <?php elseif ($esCliente): ?>
                    <p class="text-muted small mb-0">Podrá calificar y recomendar este producto después de comprarlo.</p>
                <?php elseif ($usuarioSesion === null): ?>
                    <p class="text-muted small mb-0"><a href="<?= e(url('login.php')) ?>">Inicie sesión</a> para calificar productos que haya comprado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5">Recomendaciones</h2>
                <?php if ($puedeOpinar): ?>
                    <form method="post" class="mb-3" data-validar data-confirmar="¿Desea publicar su recomendación?">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="recomendar">
                        <div class="mb-2 campo">
                            <label class="form-label" for="comentario">Escriba su recomendación</label>
                            <textarea class="form-control" id="comentario" name="comentario" rows="3" maxlength="1000" required data-regla="texto:1000" placeholder="¿Qué le pareció el producto?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Publicar recomendación</button>
                    </form>
                <?php endif; ?>

                <?php if (!$recomendaciones): ?>
                    <p class="text-muted">Aún no hay recomendaciones para este producto.</p>
                <?php endif; ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recomendaciones as $recomendacion): ?>
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <strong><?= e($recomendacion['nombres'] . ' ' . mb_substr($recomendacion['apellido_paterno'], 0, 1, 'UTF-8') . '.') ?></strong>
                                <small class="text-muted"><?= e(formatoFecha($recomendacion['fecha'])) ?></small>
                            </div>
                            <?php if ($recomendacion['estrellas'] !== null): ?><div class="small"><?= estrellasHtml((float) $recomendacion['estrellas']) ?></div><?php endif; ?>
                            <p class="mb-0"><?= nl2br(e($recomendacion['comentario'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($relacionados): ?>
<section>
    <h2 class="h5 mb-3">Productos relacionados</h2>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
        <?php foreach ($relacionados as $relacionado): ?>
            <?= tarjetaProductoHtml($relacionado) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
