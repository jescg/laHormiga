<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
requiereRol(ROL_INVENTARIOS, ROL_ADMINISTRADOR);

if (esPost()) {
    verificarCsrf();
    $productoId = entradaEntero('id');
    $producto = obtenerUno('SELECT id, nombre FROM productos WHERE id = ?', [$productoId]);
    $accion = entrada('accion');

    if ($producto === null) {
        mensajeFlash('error', 'El producto no existe.');
    } elseif ($accion === 'precio') {
        $precio = entrada('precio');
        if (validarPrecio($precio)) {
            consulta('UPDATE productos SET precio = ? WHERE id = ?', [$precio, $productoId]);
            mensajeFlash('exito', 'Se actualizó el precio de «' . $producto['nombre'] . '» a ' . formatoMoneda($precio) . '.');
        } else {
            mensajeFlash('error', 'El precio debe ser mayor a 0 y tener hasta dos decimales.');
        }
    } elseif ($accion === 'stock') {
        $stock = entrada('stock');
        if (validarEnteroRango($stock, 0, 999999)) {
            consulta('UPDATE productos SET stock = ? WHERE id = ?', [(int) $stock, $productoId]);
            mensajeFlash('exito', 'Se actualizó el stock de «' . $producto['nombre'] . '» a ' . (int) $stock . ' unidades.');
        } else {
            mensajeFlash('error', 'El stock debe ser un número entero entre 0 y 999999.');
        }
    }
    redirigir('/inventario/productos.php?' . http_build_query(['buscar' => entrada('buscar'), 'categoria' => entrada('categoria')]));
}

$filtroBusqueda = mb_substr(entrada('buscar'), 0, 100, 'UTF-8');
$filtroCategoria = entradaEntero('categoria');
$condiciones = ' WHERE 1 = 1';
$parametros = [];
if ($filtroBusqueda !== '') {
    $condiciones .= ' AND (p.nombre LIKE ? OR p.sku LIKE ?)';
    $parametros[] = '%' . $filtroBusqueda . '%';
    $parametros[] = '%' . $filtroBusqueda . '%';
}
if ($filtroCategoria > 0) {
    $condiciones .= ' AND p.categoria_id = ?';
    $parametros[] = $filtroCategoria;
}
$productos = obtenerTodos(
    'SELECT p.id, p.sku, p.nombre, p.precio, p.stock, p.activo, c.nombre AS categoria,
            (SELECT i.ruta FROM producto_imagenes i WHERE i.producto_id = p.id ORDER BY i.orden, i.id LIMIT 1) AS imagen,
            (SELECT COUNT(*) FROM producto_imagenes i WHERE i.producto_id = p.id) AS total_imagenes
       FROM productos p JOIN categorias c ON c.id = p.categoria_id' . $condiciones . ' ORDER BY p.nombre',
    $parametros
);
$categorias = obtenerTodos('SELECT id, nombre FROM categorias ORDER BY nombre');
$productosStockBajo = (int) obtenerValor('SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock < 5');

$tituloPagina = 'Inventario de productos';
$plantilla = 'panel';
$seccionActiva = 'productos';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Inventario de productos</h1>
    <?php if ($productosStockBajo > 0): ?><span class="badge text-bg-danger"><?= $productosStockBajo ?> productos con menos de 5 unidades</span><?php endif; ?>
</div>

<form class="card card-body mb-3" method="get" data-validar>
    <div class="row g-2 align-items-end">
        <div class="col-md-5 campo">
            <label class="form-label" for="buscar">Buscar producto</label>
            <input class="form-control" type="search" id="buscar" name="buscar" value="<?= e($filtroBusqueda) ?>" placeholder="Nombre o SKU" data-regla="busqueda">
        </div>
        <div class="col-md-4 campo">
            <label class="form-label" for="categoria">Categoría</label>
            <select class="form-select" id="categoria" name="categoria" data-regla="entero:0:999999">
                <option value="0">Todas</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= (int) $categoria['id'] ?>" <?= $filtroCategoria === (int) $categoria['id'] ? 'selected' : '' ?>><?= e($categoria['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-dark flex-grow-1">Buscar</button>
            <?php if ($filtroBusqueda !== '' || $filtroCategoria > 0): ?><a class="btn btn-outline-secondary" href="<?= e(url('inventario/productos.php')) ?>">Limpiar</a><?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th></th><th>SKU</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Imágenes</th></tr></thead>
            <tbody>
            <?php foreach ($productos as $producto): ?>
                <tr class="<?= $producto['activo'] ? '' : 'table-secondary' ?>">
                    <td><img class="miniatura rounded" src="<?= e(rutaImagen($producto['imagen'])) ?>" alt=""></td>
                    <td><?= e($producto['sku']) ?></td>
                    <td><?= e($producto['nombre']) ?><?= $producto['activo'] ? '' : ' <small class="text-muted">(inactivo)</small>' ?></td>
                    <td><?= e($producto['categoria']) ?></td>
                    <td>
                        <form method="post" class="d-flex gap-1" data-validar data-confirmar="¿Desea cambiar el precio de «<?= e($producto['nombre']) ?>»?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="precio">
                            <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                            <input type="hidden" name="buscar" value="<?= e($filtroBusqueda) ?>">
                            <input type="hidden" name="categoria" value="<?= (int) $filtroCategoria ?>">
                            <div class="campo">
                                <input class="form-control form-control-sm" style="width:110px" type="text" name="precio" value="<?= e($producto['precio']) ?>" inputmode="decimal" aria-label="Precio" data-regla="precio">
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" class="d-flex gap-1" data-validar data-confirmar="¿Desea cambiar el stock de «<?= e($producto['nombre']) ?>»?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="stock">
                            <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                            <input type="hidden" name="buscar" value="<?= e($filtroBusqueda) ?>">
                            <input type="hidden" name="categoria" value="<?= (int) $filtroCategoria ?>">
                            <div class="campo">
                                <input class="form-control form-control-sm <?= $producto['stock'] < 5 ? 'border-danger' : '' ?>" style="width:90px" type="number" name="stock" min="0" max="999999" value="<?= (int) $producto['stock'] ?>" aria-label="Stock" data-regla="entero:0:999999">
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                        </form>
                    </td>
                    <td><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('inventario/imagenes.php?id=' . (int) $producto['id'])) ?>"><i class="bi bi-images"></i> <?= (int) $producto['total_imagenes'] ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$productos): ?><tr><td colspan="7" class="text-muted">No se encontraron productos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
