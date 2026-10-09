<?php
/**
 * Encabezado común (Bootstrap 5).
 * Variables esperadas: $tituloPagina (string), $plantilla ('tienda' | 'panel'), $seccionActiva (string)
 */
$tituloPagina  = $tituloPagina ?? NOMBRE_TIENDA;
$plantilla     = $plantilla ?? 'tienda';
$seccionActiva = $seccionActiva ?? '';
$usuarioSesion = usuarioActual();
$terminoBusqueda = is_string($_GET['buscar'] ?? null) ? $_GET['buscar'] : '';

function enlacePanel(string $ruta, string $texto, string $clave, string $seccionActiva): string
{
    $clase = 'list-group-item list-group-item-action' . ($clave === $seccionActiva ? ' active' : '');
    return '<a class="' . $clase . '" href="' . e(url($ruta)) . '">' . e($texto) . '</a>';
}
?>
<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina) ?> | <?= e(NOMBRE_TIENDA) ?></title>
    <link rel="icon" href="<?= e(url('assets/img/icono.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/estilos.css')) ?>">
    <meta name="token-csrf" content="<?= e(tokenCsrf()) ?>">
    <meta name="url-base" content="<?= e(URL_BASE) ?>">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container<?= $plantilla === 'panel' ? '-fluid' : '' ?>">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('/')) ?>">
            <img src="<?= e(url('assets/img/logotipo.svg')) ?>" alt="">
            La Hormiga
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Mostrar menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuPrincipal">
            <?php if ($plantilla === 'tienda'): ?>
                <ul class="navbar-nav me-3">
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('tienda/catalogo.php')) ?>">Catálogo</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Categorías</a>
                        <ul class="dropdown-menu">
                            <?php foreach (categoriasActivas() as $categoria): ?>
                                <li><a class="dropdown-item" href="<?= e(url('tienda/catalogo.php?categoria=' . (int) $categoria['id'])) ?>"><?= e($categoria['nombre']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
                <form class="d-flex flex-grow-1 me-lg-3 my-2 my-lg-0" action="<?= e(url('tienda/catalogo.php')) ?>" method="get" role="search" data-validar>
                    <div class="input-group campo">
                        <input class="form-control" type="search" name="buscar" value="<?= e($terminoBusqueda) ?>" placeholder="Buscar productos" aria-label="Buscar" data-regla="busqueda">
                        <button class="btn btn-hormiga" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            <?php else: ?>
                <span class="navbar-text me-auto">Panel de <?= e($usuarioSesion['rol_nombre'] ?? '') ?></span>
            <?php endif; ?>

            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($plantilla === 'tienda' && ($usuarioSesion === null || $usuarioSesion['rol'] === ROL_CLIENTE)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('tienda/carrito.php')) ?>">
                            <i class="bi bi-cart3"></i> Carrito
                            <span class="badge bg-warning text-dark" id="contadorCarrito"><?= totalArticulosCarrito() ?></span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($usuarioSesion === null): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>">Iniciar sesión</a></li>
                    <li class="nav-item"><a class="btn btn-outline-light btn-sm ms-lg-2" href="<?= e(url('registro.php')) ?>">Crear cuenta</a></li>
                <?php else: ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= e(explode(' ', $usuarioSesion['nombres'])[0]) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($usuarioSesion['rol'] === ROL_CLIENTE): ?>
                                <li><a class="dropdown-item" href="<?= e(url('tienda/cuenta.php')) ?>">Mi cuenta</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('tienda/pedidos.php')) ?>">Mis pedidos</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('tienda/direcciones.php')) ?>">Mis direcciones</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('tienda/metodos_pago.php')) ?>">Métodos de pago</a></li>
                            <?php elseif ($usuarioSesion['rol'] === ROL_ADMINISTRADOR): ?>
                                <li><a class="dropdown-item" href="<?= e(url('admin/index.php')) ?>">Panel de administración</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('tienda/catalogo.php')) ?>">Ver tienda</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?= e(url('inventario/productos.php')) ?>">Panel de inventarios</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('tienda/catalogo.php')) ?>">Ver tienda</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="<?= e(url('logout.php')) ?>" method="post" data-confirmar="¿Desea cerrar su sesión?">
                                    <?= campoCsrf() ?>
                                    <button type="submit" class="dropdown-item">Cerrar sesión</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($plantilla === 'panel'): ?>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-3 col-lg-2 bg-white border-end p-3 barra-lateral">
            <?php if ($usuarioSesion['rol'] === ROL_ADMINISTRADOR): ?>
                <h6 class="text-uppercase text-muted small mt-2">General</h6>
                <div class="list-group list-group-flush mb-3">
                    <?= enlacePanel('admin/index.php', 'Resumen', 'resumen', $seccionActiva) ?>
                    <?= enlacePanel('admin/reportes.php', 'Reporte de ventas', 'reportes', $seccionActiva) ?>
                </div>
                <h6 class="text-uppercase text-muted small">Administración</h6>
                <div class="list-group list-group-flush mb-3">
                    <?= enlacePanel('admin/usuarios.php', 'Usuarios', 'usuarios', $seccionActiva) ?>
                    <?= enlacePanel('admin/productos.php', 'Productos', 'productos', $seccionActiva) ?>
                    <?= enlacePanel('admin/resenas.php', 'Calificaciones y recomendaciones', 'resenas', $seccionActiva) ?>
                    <?= enlacePanel('inventario/ventas.php', 'Pedidos', 'ventas', $seccionActiva) ?>
                </div>
                <h6 class="text-uppercase text-muted small">Catálogos</h6>
                <div class="list-group list-group-flush mb-3">
                    <?= enlacePanel('admin/categorias.php', 'Categorías', 'categorias', $seccionActiva) ?>
                    <?= enlacePanel('admin/paises.php', 'Países', 'paises', $seccionActiva) ?>
                    <?= enlacePanel('admin/tipos_tarjeta.php', 'Tipos de tarjeta', 'tipos_tarjeta', $seccionActiva) ?>
                    <?= enlacePanel('admin/estatus.php', 'Estatus de pedido', 'estatus', $seccionActiva) ?>
                </div>
            <?php else: ?>
                <h6 class="text-uppercase text-muted small mt-2">Inventarios</h6>
                <div class="list-group list-group-flush mb-3">
                    <?= enlacePanel('inventario/productos.php', 'Productos', 'productos', $seccionActiva) ?>
                    <?= enlacePanel('inventario/ventas.php', 'Ventas y pedidos', 'ventas', $seccionActiva) ?>
                </div>
            <?php endif; ?>
            <div class="list-group list-group-flush">
                <a class="list-group-item list-group-item-action" href="<?= e(url('tienda/catalogo.php')) ?>"><i class="bi bi-shop"></i> Ver tienda</a>
            </div>
        </aside>
        <main class="col-md-9 col-lg-10 p-4" id="contenido">
<?php else: ?>
<main class="container py-4" id="contenido">
<?php endif; ?>

<div id="contenedorMensajes">
    <?php foreach (obtenerMensajes() as $mensaje):
        $claseAlerta = ['exito' => 'success', 'error' => 'danger', 'aviso' => 'warning', 'info' => 'info'][$mensaje['tipo']] ?? 'info'; ?>
        <div class="alert alert-<?= $claseAlerta ?> alert-dismissible fade show" role="alert">
            <?= e($mensaje['texto']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endforeach; ?>
</div>
