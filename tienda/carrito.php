<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
$usuario = requiereRol(ROL_CLIENTE);

// Alternativa sin JavaScript para vaciar el carrito
if (esPost() && entrada('accion') === 'vaciar') {
    verificarCsrf();
    consulta('DELETE FROM carrito_items WHERE usuario_id = ?', [$usuario['id']]);
    mensajeFlash('exito', 'Su carrito se vació.');
    redirigir('/tienda/carrito.php');
}

$articulos = obtenerTodos(
    'SELECT ci.producto_id, ci.cantidad, p.nombre, p.precio, p.dias_entrega, p.activo, c.nombre AS categoria,
            (SELECT i.ruta FROM producto_imagenes i WHERE i.producto_id = p.id ORDER BY i.orden, i.id LIMIT 1) AS imagen
       FROM carrito_items ci
       JOIN productos p ON p.id = ci.producto_id
       JOIN categorias c ON c.id = p.categoria_id
      WHERE ci.usuario_id = ?
      ORDER BY ci.agregado_en DESC',
    [$usuario['id']]
);
$subtotal = 0;
$totalArticulos = 0;
foreach ($articulos as $articulo) {
    $subtotal += $articulo['precio'] * $articulo['cantidad'];
    $totalArticulos += $articulo['cantidad'];
}

$tituloPagina = 'Carrito de compras';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<h1 class="h3 mb-3">Carrito de compras</h1>

<?php if (!$articulos): ?>
    <div class="alert alert-info">
        Su carrito está vacío. <a class="alert-link" href="<?= e(url('tienda/catalogo.php')) ?>">Seguir comprando</a>.
    </div>
<?php else: ?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0">
                <table class="table align-middle mb-0" id="tablaCarrito">
                    <thead class="table-light">
                        <tr><th colspan="2">Producto</th><th style="width:130px">Cantidad</th><th class="text-end">Importe</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($articulos as $articulo): ?>
                        <tr data-producto="<?= (int) $articulo['producto_id'] ?>">
                            <td style="width:90px"><img class="miniatura-grande rounded" src="<?= e(rutaImagen($articulo['imagen'])) ?>" alt=""></td>
                            <td>
                                <a href="<?= e(url('tienda/producto.php?id=' . (int) $articulo['producto_id'])) ?>"><?= e($articulo['nombre']) ?></a><br>
                                <small class="text-muted"><?= e(formatoMoneda($articulo['precio'])) ?> c/u &middot; <?= e(textoEntrega((int) $articulo['dias_entrega'])) ?></small>
                                <?php if ((int) $articulo['activo'] !== 1): ?><br><small class="text-danger">Este producto ya no está disponible. Elimínelo para continuar.</small><?php endif; ?>
                            </td>
                            <td>
                                <div class="campo">
                                    <select class="form-select form-select-sm" aria-label="Cantidad" data-cantidad-carrito data-regla="entero:1:99">
                                        <?php for ($cantidad = 1; $cantidad <= max(10, (int) $articulo['cantidad']); $cantidad++): ?>
                                            <option value="<?= $cantidad ?>" <?= $cantidad === (int) $articulo['cantidad'] ? 'selected' : '' ?>><?= $cantidad ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </td>
                            <td class="text-end fw-semibold" data-importe><?= e(formatoMoneda($articulo['precio'] * $articulo['cantidad'])) ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" data-eliminar-carrito data-nombre="<?= e($articulo['nombre']) ?>" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <form method="post" class="mt-2" data-confirmar="¿Desea eliminar todos los productos de su carrito?">
            <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="vaciar">
            <button type="submit" class="btn btn-sm btn-outline-danger">Vaciar carrito</button>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h2 class="h5">Resumen</h2>
                <p class="mb-1">Subtotal (<span data-texto-articulos><?= $totalArticulos ?> <?= $totalArticulos === 1 ? 'producto' : 'productos' ?></span>)</p>
                <p class="fs-4 fw-bold" data-subtotal><?= e(formatoMoneda($subtotal)) ?></p>
                <p class="small text-success"><i class="bi bi-truck"></i> Envío gratis</p>
                <a class="btn btn-hormiga w-100" href="<?= e(url('tienda/checkout.php')) ?>">Proceder al pago</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
