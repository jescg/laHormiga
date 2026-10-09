<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
requiereRol(ROL_ADMINISTRADOR);

$categorias = obtenerTodos('SELECT id, nombre, activo FROM categorias ORDER BY nombre');
$accion = entrada('accion', 'listar');
$productoId = entradaEntero('id');
$errores = [];
$valores = ['sku' => '', 'nombre' => '', 'descripcion' => '', 'precio' => '', 'stock' => '0', 'categoria_id' => '', 'dias_entrega' => '3', 'activo' => 1];

if ($productoId > 0) {
    $productoExistente = obtenerUno('SELECT * FROM productos WHERE id = ?', [$productoId]);
    if ($productoExistente === null) {
        mensajeFlash('error', 'El producto no existe.');
        redirigir('/admin/productos.php');
    }
    $valores = $productoExistente;
}

if (esPost()) {
    verificarCsrf();

    if ($productoId > 0 && procesarAccionImagenes($productoId)) {
        redirigir('/admin/productos.php?accion=editar&id=' . $productoId);
    }

    if ($accion === 'guardar') {
        $valores = [
            'sku'          => strtoupper(entrada('sku')),
            'nombre'       => entrada('nombre'),
            'descripcion'  => entrada('descripcion'),
            'precio'       => entrada('precio'),
            'stock'        => entrada('stock'),
            'categoria_id' => entrada('categoria_id'),
            'dias_entrega' => entrada('dias_entrega'),
            'activo'       => entrada('activo') === '1' ? 1 : 0,
        ];
        if (!preg_match('/^[A-Z0-9-]{3,30}$/', $valores['sku'])) {
            $errores[] = 'El identificador (SKU) debe tener de 3 a 30 caracteres: letras, números o guiones.';
        } elseif (obtenerValor('SELECT 1 FROM productos WHERE sku = ? AND id <> ?', [$valores['sku'], $productoId])) {
            $errores[] = 'Ya existe un producto con ese identificador.';
        }
        if (!validarTextoRequerido($valores['nombre'], 150)) {
            $errores[] = 'El nombre es obligatorio (máximo 150 caracteres).';
        }
        if (mb_strlen($valores['descripcion'], 'UTF-8') > 5000) {
            $errores[] = 'La descripción no debe exceder 5000 caracteres.';
        }
        if (!validarPrecio($valores['precio'])) {
            $errores[] = 'El precio debe ser mayor a 0 y tener hasta dos decimales.';
        }
        if (!validarEnteroRango($valores['stock'], 0, 999999)) {
            $errores[] = 'El stock debe ser un número entero entre 0 y 999999.';
        }
        if (!validarEnteroRango($valores['dias_entrega'], 1, 60)) {
            $errores[] = 'Los días estimados de entrega deben estar entre 1 y 60.';
        }
        if (!obtenerValor('SELECT 1 FROM categorias WHERE id = ?', [(int) $valores['categoria_id']])) {
            $errores[] = 'Seleccione una categoría válida.';
        }

        if (!$errores) {
            $parametros = [
                $valores['sku'], $valores['nombre'], $valores['descripcion'] !== '' ? $valores['descripcion'] : null, $valores['precio'],
                (int) $valores['stock'], (int) $valores['categoria_id'], (int) $valores['dias_entrega'], $valores['activo'],
            ];
            if ($productoId > 0) {
                consulta(
                    'UPDATE productos SET sku = ?, nombre = ?, descripcion = ?, precio = ?, stock = ?, categoria_id = ?, dias_entrega = ?, activo = ? WHERE id = ?',
                    array_merge($parametros, [$productoId])
                );
                mensajeFlash('exito', 'El producto se actualizó correctamente.');
            } else {
                consulta(
                    'INSERT INTO productos (sku, nombre, descripcion, precio, stock, categoria_id, dias_entrega, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    $parametros
                );
                $productoId = (int) conexion()->lastInsertId();
                $erroresImagenes = [];
                $guardadas = guardarImagenesProducto($productoId, 'imagenes', $erroresImagenes);
                foreach ($erroresImagenes as $errorImagen) {
                    mensajeFlash('error', $errorImagen);
                }
                mensajeFlash('exito', 'El producto se creó correctamente' . ($guardadas ? ' con ' . $guardadas . ($guardadas === 1 ? ' imagen.' : ' imágenes.') : '.'));
            }
            redirigir('/admin/productos.php?accion=editar&id=' . $productoId);
        }
        $accion = $productoId > 0 ? 'editar' : 'nuevo';
    } elseif ($accion === 'eliminar') {
        try {
            $rutasImagenes = obtenerTodos('SELECT ruta FROM producto_imagenes WHERE producto_id = ?', [$productoId]);
            conexion()->beginTransaction();
            consulta('DELETE FROM productos WHERE id = ?', [$productoId]);
            conexion()->commit();
            foreach ($rutasImagenes as $imagen) {
                $rutaFisica = RAIZ_PUBLICA . '/' . $imagen['ruta'];
                if (strpos($imagen['ruta'], CARPETA_IMAGENES . '/') === 0 && is_file($rutaFisica)) {
                    unlink($rutaFisica);
                }
            }
            mensajeFlash('exito', 'El producto se eliminó correctamente.');
        } catch (PDOException $excepcion) {
            if (conexion()->inTransaction()) {
                conexion()->rollBack();
            }
            consulta('UPDATE productos SET activo = 0 WHERE id = ?', [$productoId]);
            mensajeFlash('aviso', 'El producto tiene ventas registradas, por lo que se desactivó en lugar de eliminarse.');
        }
        redirigir('/admin/productos.php');
    }
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
    'SELECT p.id, p.sku, p.nombre, p.precio, p.stock, p.dias_entrega, p.activo, c.nombre AS categoria,
            (SELECT i.ruta FROM producto_imagenes i WHERE i.producto_id = p.id ORDER BY i.orden, i.id LIMIT 1) AS imagen,
            (SELECT COUNT(*) FROM producto_imagenes i WHERE i.producto_id = p.id) AS total_imagenes,
            (SELECT AVG(ca.estrellas) FROM calificaciones ca WHERE ca.producto_id = p.id) AS promedio
       FROM productos p JOIN categorias c ON c.id = p.categoria_id' . $condiciones . ' ORDER BY p.id DESC',
    $parametros
);

$tituloPagina = 'Productos';
$plantilla = 'panel';
$seccionActiva = 'productos';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= $productoId > 0 ? 'Editar producto' : 'Nuevo producto' ?></h1>
        <div>
            <?php if ($productoId > 0): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('tienda/producto.php?id=' . $productoId)) ?>" target="_blank" rel="noopener">Ver en la tienda</a><?php endif; ?>
            <a class="ms-2" href="<?= e(url('admin/productos.php')) ?>">&larr; Volver a la lista</a>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><strong>Datos del producto</strong></div>
                <div class="card-body">
                    <?php if ($errores): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('admin/productos.php')) ?>" enctype="multipart/form-data" data-validar data-confirmar="¿Desea guardar este producto?">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="guardar">
                        <input type="hidden" name="id" value="<?= (int) $productoId ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6 campo">
                                <label class="form-label" for="sku">Identificador interno (SKU)</label>
                                <input class="form-control" type="text" id="sku" name="sku" value="<?= e($valores['sku']) ?>" maxlength="30" required data-regla="sku" data-mayusculas placeholder="ELE-0001">
                            </div>
                            <div class="col-md-6 campo">
                                <label class="form-label" for="categoria_id">Categoría</label>
                                <select class="form-select" id="categoria_id" name="categoria_id" required data-regla="seleccion">
                                    <option value="">Seleccione una categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?= (int) $categoria['id'] ?>" <?= (string) $valores['categoria_id'] === (string) $categoria['id'] ? 'selected' : '' ?>><?= e($categoria['nombre']) ?><?= $categoria['activo'] ? '' : ' (inactiva)' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 campo">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input class="form-control" type="text" id="nombre" name="nombre" value="<?= e($valores['nombre']) ?>" maxlength="150" required data-regla="texto:150">
                            </div>
                            <div class="col-12 campo">
                                <label class="form-label" for="descripcion">Descripción <span class="text-muted">(opcional)</span></label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="4" maxlength="5000" data-regla="texto:5000" data-opcional><?= e($valores['descripcion']) ?></textarea>
                            </div>
                            <div class="col-md-4 campo">
                                <label class="form-label" for="precio">Precio (MXN)</label>
                                <input class="form-control" type="text" id="precio" name="precio" value="<?= e($valores['precio']) ?>" inputmode="decimal" required data-regla="precio" placeholder="0.00">
                            </div>
                            <div class="col-md-4 campo">
                                <label class="form-label" for="stock">Stock (unidades)</label>
                                <input class="form-control" type="number" id="stock" name="stock" value="<?= e($valores['stock']) ?>" min="0" max="999999" required data-regla="entero:0:999999">
                            </div>
                            <div class="col-md-4 campo">
                                <label class="form-label" for="dias_entrega">Días de entrega</label>
                                <input class="form-control" type="number" id="dias_entrega" name="dias_entrega" value="<?= e($valores['dias_entrega']) ?>" min="1" max="60" required data-regla="entero:1:60">
                            </div>
                            <?php if ($productoId === 0): ?>
                                <div class="col-12 campo">
                                    <label class="form-label" for="imagenes">Imágenes <span class="text-muted">(puede seleccionar varias)</span></label>
                                    <input class="form-control" type="file" id="imagenes" name="imagenes[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-regla="imagenes" data-vista-previa="vistaPrevia">
                                    <div class="vista-previa" id="vistaPrevia"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= $valores['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Publicado en la tienda</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar producto</button>
                        <a class="btn btn-secondary" href="<?= e(url('admin/productos.php')) ?>">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
        <?php if ($productoId > 0): ?>
            <div class="col-lg-6">
                <div class="card"><div class="card-body">
                    <?php $urlFormularioImagenes = url('admin/productos.php?id=' . $productoId); require dirname(__DIR__) . '/includes/gestion_imagenes.php'; ?>
                </div></div>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Productos</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/productos.php?accion=nuevo')) ?>"><i class="bi bi-plus-lg"></i> Nuevo producto</a>
    </div>
    <form class="card card-body mb-3" method="get" data-validar>
        <div class="row g-2 align-items-end">
            <div class="col-md-5 campo">
                <label class="form-label" for="buscar">Buscar</label>
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
            <div class="col-md-3"><button type="submit" class="btn btn-dark w-100">Filtrar</button></div>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th></th><th>SKU</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Entrega</th><th>Calificación</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($productos as $producto): ?>
                    <tr class="<?= $producto['activo'] ? '' : 'table-secondary' ?>">
                        <td><img class="miniatura rounded" src="<?= e(rutaImagen($producto['imagen'])) ?>" alt=""></td>
                        <td><?= e($producto['sku']) ?></td>
                        <td><?= e($producto['nombre']) ?><br><small class="text-muted"><?= (int) $producto['total_imagenes'] ?> imágenes</small></td>
                        <td><?= e($producto['categoria']) ?></td>
                        <td><?= e(formatoMoneda($producto['precio'])) ?></td>
                        <td><span class="badge <?= $producto['stock'] < 5 ? 'text-bg-danger' : 'text-bg-light border' ?>"><?= (int) $producto['stock'] ?></span></td>
                        <td><?= (int) $producto['dias_entrega'] ?> días</td>
                        <td class="small"><?= estrellasHtml((float) $producto['promedio']) ?></td>
                        <td><?= $producto['activo'] ? '<span class="badge text-bg-success">Publicado</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/productos.php?accion=editar&id=' . (int) $producto['id'])) ?>">Editar</a>
                                <form method="post" action="<?= e(url('admin/productos.php')) ?>" data-confirmar="¿Desea eliminar «<?= e($producto['nombre']) ?>»? Esta acción no se puede deshacer.">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$productos): ?><tr><td colspan="10" class="text-muted">No se encontraron productos.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
