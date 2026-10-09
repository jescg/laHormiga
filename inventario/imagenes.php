<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
requiereRol(ROL_INVENTARIOS, ROL_ADMINISTRADOR);

$productoId = entradaEntero('id');
$producto = obtenerUno('SELECT id, sku, nombre FROM productos WHERE id = ?', [$productoId]);
if ($producto === null) {
    mensajeFlash('error', 'El producto no existe.');
    redirigir('/inventario/productos.php');
}

if (esPost()) {
    verificarCsrf();
    procesarAccionImagenes($productoId);
    redirigir('/inventario/imagenes.php?id=' . $productoId);
}

$tituloPagina = 'Imágenes de ' . $producto['nombre'];
$plantilla = 'panel';
$seccionActiva = 'productos';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Imágenes del producto</h1>
    <a href="<?= e(url('inventario/productos.php')) ?>">&larr; Volver al inventario</a>
</div>
<div class="card">
    <div class="card-body">
        <p><strong><?= e($producto['nombre']) ?></strong> <span class="text-muted">&middot; SKU <?= e($producto['sku']) ?></span></p>
        <?php $urlFormularioImagenes = url('inventario/imagenes.php?id=' . $productoId); require dirname(__DIR__) . '/includes/gestion_imagenes.php'; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
