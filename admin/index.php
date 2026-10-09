<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
$usuario = requiereRol(ROL_ADMINISTRADOR);

$ventasMes = obtenerUno(
    'SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS total FROM pedidos WHERE YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())'
);
$ventasHoy = (float) obtenerValor('SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE DATE(fecha) = CURDATE()');
$totalClientes = (int) obtenerValor('SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.clave = ?', [ROL_CLIENTE]);
$totalProductos = (int) obtenerValor('SELECT COUNT(*) FROM productos WHERE activo = 1');
$productosStockBajo = obtenerTodos('SELECT id, sku, nombre, stock FROM productos WHERE activo = 1 AND stock < 5 ORDER BY stock, nombre LIMIT 8');
$ultimosPedidos = obtenerTodos(
    'SELECT p.id, p.fecha, p.total, p.estatus_id, e.nombre AS estatus, u.nombres, u.apellido_paterno
       FROM pedidos p JOIN estatus_pedido e ON e.id = p.estatus_id JOIN usuarios u ON u.id = p.usuario_id
      ORDER BY p.fecha DESC LIMIT 8'
);

$tituloPagina = 'Panel de administración';
$plantilla = 'panel';
$seccionActiva = 'resumen';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Resumen</h1>
    <span class="text-muted"><?= e(formatoFecha(date('Y-m-d'))) ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl"><div class="card text-bg-warning"><div class="card-body"><small>Ventas del mes</small><div class="fs-4 fw-bold"><?= e(formatoMoneda($ventasMes['total'])) ?></div></div></div></div>
    <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><small class="text-muted">Pedidos del mes</small><div class="fs-4 fw-bold"><?= (int) $ventasMes['pedidos'] ?></div></div></div></div>
    <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><small class="text-muted">Ventas de hoy</small><div class="fs-4 fw-bold"><?= e(formatoMoneda($ventasHoy)) ?></div></div></div></div>
    <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><small class="text-muted">Clientes</small><div class="fs-4 fw-bold"><?= $totalClientes ?></div></div></div></div>
    <div class="col-sm-6 col-xl"><div class="card"><div class="card-body"><small class="text-muted">Productos publicados</small><div class="fs-4 fw-bold"><?= $totalProductos ?></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between"><strong>Últimos pedidos</strong><a href="<?= e(url('inventario/ventas.php')) ?>">Ver ventas</a></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Estatus</th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimosPedidos as $pedido): ?>
                        <tr>
                            <td>n.º <?= (int) $pedido['id'] ?></td>
                            <td><?= e(formatoFecha($pedido['fecha'], true)) ?></td>
                            <td><?= e($pedido['nombres'] . ' ' . $pedido['apellido_paterno']) ?></td>
                            <td><?= e(formatoMoneda($pedido['total'])) ?></td>
                            <td><span class="badge <?= claseEstatus((int) $pedido['estatus_id']) ?>"><?= e($pedido['estatus']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ultimosPedidos): ?><tr><td colspan="5" class="text-muted">Aún no hay pedidos.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between"><strong>Productos con stock bajo</strong><a href="<?= e(url('admin/productos.php')) ?>">Ver productos</a></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>SKU</th><th>Producto</th><th>Stock</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($productosStockBajo as $producto): ?>
                        <tr>
                            <td><?= e($producto['sku']) ?></td>
                            <td><?= e($producto['nombre']) ?></td>
                            <td><span class="badge text-bg-danger"><?= (int) $producto['stock'] ?></span></td>
                            <td><a href="<?= e(url('admin/productos.php?accion=editar&id=' . (int) $producto['id'])) ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$productosStockBajo): ?><tr><td colspan="4" class="text-muted">Todos los productos tienen existencias suficientes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
