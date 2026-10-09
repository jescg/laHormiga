<?php
require_once dirname(__DIR__) . '/includes/inicio.php';

const PRODUCTOS_POR_PAGINA = 12;

$categoriaId = entradaEntero('categoria');
$terminoBusqueda = mb_substr(entrada('buscar'), 0, 100, 'UTF-8');
$orden = entrada('orden', 'relevancia');
$paginaActual = max(1, entradaEntero('pagina', 1));

$ordenamientos = [
    'relevancia'  => 'p.id DESC',
    'precio_asc'  => 'p.precio ASC',
    'precio_desc' => 'p.precio DESC',
    'calificacion'=> 'promedio DESC',
    'nombre'      => 'p.nombre ASC',
];
if (!isset($ordenamientos[$orden])) {
    $orden = 'relevancia';
}

$condiciones = '';
$parametros = [];
if ($categoriaId > 0) {
    $condiciones .= ' AND p.categoria_id = ?';
    $parametros[] = $categoriaId;
}
if ($terminoBusqueda !== '') {
    $condiciones .= ' AND (p.nombre LIKE ? OR p.descripcion LIKE ?)';
    $parametros[] = '%' . $terminoBusqueda . '%';
    $parametros[] = '%' . $terminoBusqueda . '%';
}

$totalProductos = (int) obtenerValor(
    'SELECT COUNT(*) FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.activo = 1 AND c.activo = 1' . $condiciones,
    $parametros
);
$totalPaginas = max(1, (int) ceil($totalProductos / PRODUCTOS_POR_PAGINA));
$paginaActual = min($paginaActual, $totalPaginas);
$desplazamiento = ($paginaActual - 1) * PRODUCTOS_POR_PAGINA;

$productos = obtenerTodos(
    SQL_PRODUCTOS_TIENDA . $condiciones . ' ORDER BY ' . $ordenamientos[$orden] . ' LIMIT ' . PRODUCTOS_POR_PAGINA . ' OFFSET ' . $desplazamiento,
    $parametros
);

$categoriaSeleccionada = null;
foreach (categoriasActivas() as $categoria) {
    if ((int) $categoria['id'] === $categoriaId) {
        $categoriaSeleccionada = $categoria;
    }
}

function enlaceCatalogo(array $cambios): string
{
    $parametrosActuales = array_filter([
        'categoria' => $_GET['categoria'] ?? null,
        'buscar'    => $_GET['buscar'] ?? null,
        'orden'     => $_GET['orden'] ?? null,
    ], 'is_string');
    $parametrosNuevos = array_filter(array_merge($parametrosActuales, $cambios), function ($valor) {
        return $valor !== null && $valor !== '' && $valor !== '0';
    });
    return url('tienda/catalogo.php' . ($parametrosNuevos ? '?' . http_build_query($parametrosNuevos) : ''));
}

$tituloPagina = $categoriaSeleccionada ? $categoriaSeleccionada['nombre'] : 'Catálogo de productos';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<div class="row g-4">
    <aside class="col-md-3">
        <div class="card mb-3">
            <div class="card-header fw-semibold">Categorías</div>
            <div class="list-group list-group-flush">
                <a href="<?= e(enlaceCatalogo(['categoria' => null, 'pagina' => null])) ?>" class="list-group-item list-group-item-action <?= $categoriaId === 0 ? 'active' : '' ?>">Todas las categorías</a>
                <?php foreach (categoriasActivas() as $categoria): ?>
                    <a href="<?= e(enlaceCatalogo(['categoria' => (string) $categoria['id'], 'pagina' => null])) ?>" class="list-group-item list-group-item-action <?= $categoriaId === (int) $categoria['id'] ? 'active' : '' ?>"><?= e($categoria['nombre']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>

    <section class="col-md-9">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h4 mb-0"><?= e($categoriaSeleccionada['nombre'] ?? 'Catálogo de productos') ?></h1>
                <small class="text-muted">
                    <?= $totalProductos ?> <?= $totalProductos === 1 ? 'resultado' : 'resultados' ?>
                    <?php if ($terminoBusqueda !== ''): ?> para «<?= e($terminoBusqueda) ?>»<?php endif; ?>
                </small>
            </div>
            <form method="get" action="<?= e(url('tienda/catalogo.php')) ?>" class="d-flex gap-2" data-validar>
                <?php if ($terminoBusqueda !== ''): ?><input type="hidden" name="buscar" value="<?= e($terminoBusqueda) ?>"><?php endif; ?>
                <?php if ($categoriaId > 0): ?><input type="hidden" name="categoria" value="<?= (int) $categoriaId ?>"><?php endif; ?>
                <div class="campo">
                    <select name="orden" class="form-select form-select-sm" aria-label="Ordenar por" data-regla="requerido">
                        <option value="relevancia" <?= $orden === 'relevancia' ? 'selected' : '' ?>>Más recientes</option>
                        <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio: menor a mayor</option>
                        <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
                        <option value="calificacion" <?= $orden === 'calificacion' ? 'selected' : '' ?>>Mejor calificados</option>
                        <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre (A-Z)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Ordenar</button>
            </form>
        </div>

        <?php if (!$productos): ?>
            <div class="alert alert-info">
                No encontramos productos. <a href="<?= e(url('tienda/catalogo.php')) ?>" class="alert-link">Ver todo el catálogo</a>.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
                <?php foreach ($productos as $producto): ?>
                    <?= tarjetaProductoHtml($producto) ?>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4" aria-label="Paginación">
                    <ul class="pagination justify-content-center">
                        <?php for ($numeroPagina = 1; $numeroPagina <= $totalPaginas; $numeroPagina++): ?>
                            <li class="page-item <?= $numeroPagina === $paginaActual ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(enlaceCatalogo(['pagina' => (string) $numeroPagina])) ?>"><?= $numeroPagina ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
