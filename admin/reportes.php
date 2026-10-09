<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
requiereRol(ROL_ADMINISTRADOR);

$anioActual = (int) date('Y');
$aniosDisponibles = array_map('intval', array_column(obtenerTodos('SELECT DISTINCT YEAR(fecha) AS anio FROM pedidos ORDER BY anio DESC'), 'anio'));
if (!in_array($anioActual, $aniosDisponibles, true)) {
    array_unshift($aniosDisponibles, $anioActual);
}

$anio = entradaEntero('anio', $anioActual);
if (!in_array($anio, $aniosDisponibles, true)) {
    $anio = $anioActual;
}
$mes = entradaEntero('mes');
if ($mes < 0 || $mes > 12) {
    $mes = 0;
}
$dia = entrada('dia');
if ($dia !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || !strtotime($dia))) {
    mensajeFlash('error', 'La fecha seleccionada no es válida.');
    $dia = '';
}

// Filtro principal: un día específico, o bien año y mes
if ($dia !== '') {
    $condicion = 'DATE(p.fecha) = ?';
    $parametros = [$dia];
    $descripcionPeriodo = 'el ' . formatoFecha($dia);
} elseif ($mes > 0) {
    $condicion = 'YEAR(p.fecha) = ? AND MONTH(p.fecha) = ?';
    $parametros = [$anio, $mes];
    $descripcionPeriodo = nombreMes($mes) . ' de ' . $anio;
} else {
    $condicion = 'YEAR(p.fecha) = ?';
    $parametros = [$anio];
    $descripcionPeriodo = 'el año ' . $anio;
}

$resumen = obtenerUno(
    "SELECT COUNT(*) AS pedidos, COALESCE(SUM(p.total), 0) AS total, COALESCE(AVG(p.total), 0) AS ticket_promedio
       FROM pedidos p WHERE $condicion",
    $parametros
);
$unidades = (int) obtenerValor(
    "SELECT COALESCE(SUM(d.cantidad), 0) FROM pedido_detalle d JOIN pedidos p ON p.id = d.pedido_id WHERE $condicion",
    $parametros
);

// Ventas agrupadas: por día (si hay mes o día) o por mes (si es todo el año)
$agruparPorDia = $dia !== '' || $mes > 0;
$ventasAgrupadas = obtenerTodos(
    $agruparPorDia
        ? "SELECT DATE(p.fecha) AS periodo, COUNT(*) AS pedidos, SUM(p.total) AS total FROM pedidos p WHERE $condicion GROUP BY DATE(p.fecha) ORDER BY periodo"
        : "SELECT MONTH(p.fecha) AS periodo, COUNT(*) AS pedidos, SUM(p.total) AS total FROM pedidos p WHERE $condicion GROUP BY MONTH(p.fecha) ORDER BY periodo",
    $parametros
);
$totalMaximo = $ventasAgrupadas ? max(array_map('floatval', array_column($ventasAgrupadas, 'total'))) : 0;

$productosMasVendidos = obtenerTodos(
    "SELECT pr.sku, pr.nombre, c.nombre AS categoria, SUM(d.cantidad) AS unidades, SUM(d.cantidad * d.precio_unitario) AS importe
       FROM pedido_detalle d
       JOIN pedidos p ON p.id = d.pedido_id
       JOIN productos pr ON pr.id = d.producto_id
       JOIN categorias c ON c.id = pr.categoria_id
      WHERE $condicion
      GROUP BY pr.id, pr.sku, pr.nombre, c.nombre
      ORDER BY unidades DESC, importe DESC
      LIMIT 10",
    $parametros
);
$ventasPorCategoria = obtenerTodos(
    "SELECT c.nombre AS categoria, SUM(d.cantidad) AS unidades, SUM(d.cantidad * d.precio_unitario) AS importe
       FROM pedido_detalle d
       JOIN pedidos p ON p.id = d.pedido_id
       JOIN productos pr ON pr.id = d.producto_id
       JOIN categorias c ON c.id = pr.categoria_id
      WHERE $condicion
      GROUP BY c.id, c.nombre
      ORDER BY unidades DESC, importe DESC",
    $parametros
);

// Tipo de producto (categoría) y producto más vendido por cada mes del año seleccionado
$filasCategoriaMes = obtenerTodos(
    'SELECT MONTH(p.fecha) AS mes, c.nombre AS categoria, SUM(d.cantidad) AS unidades, SUM(d.cantidad * d.precio_unitario) AS importe
       FROM pedido_detalle d
       JOIN pedidos p ON p.id = d.pedido_id
       JOIN productos pr ON pr.id = d.producto_id
       JOIN categorias c ON c.id = pr.categoria_id
      WHERE YEAR(p.fecha) = ?
      GROUP BY MONTH(p.fecha), c.id, c.nombre
      ORDER BY mes, unidades DESC, importe DESC',
    [$anio]
);
$filasProductoMes = obtenerTodos(
    'SELECT MONTH(p.fecha) AS mes, pr.nombre AS producto, SUM(d.cantidad) AS unidades
       FROM pedido_detalle d
       JOIN pedidos p ON p.id = d.pedido_id
       JOIN productos pr ON pr.id = d.producto_id
      WHERE YEAR(p.fecha) = ?
      GROUP BY MONTH(p.fecha), pr.id, pr.nombre
      ORDER BY mes, unidades DESC',
    [$anio]
);
$masVendidoPorMes = [];
foreach ($filasCategoriaMes as $fila) {
    if (!isset($masVendidoPorMes[$fila['mes']])) {
        $masVendidoPorMes[$fila['mes']] = $fila;
    }
}
foreach ($filasProductoMes as $fila) {
    if (isset($masVendidoPorMes[$fila['mes']]) && !isset($masVendidoPorMes[$fila['mes']]['producto'])) {
        $masVendidoPorMes[$fila['mes']]['producto'] = $fila['producto'];
        $masVendidoPorMes[$fila['mes']]['unidades_producto'] = $fila['unidades'];
    }
}

$tituloPagina = 'Reporte de ventas';
$plantilla = 'panel';
$seccionActiva = 'reportes';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Reporte de ventas</h1>
    <span class="text-muted">Periodo: <?= e($descripcionPeriodo) ?></span>
</div>

<form class="card card-body mb-3" method="get" data-validar>
    <div class="row g-2 align-items-end">
        <div class="col-md-2 campo">
            <label class="form-label" for="anio">Año</label>
            <select class="form-select" id="anio" name="anio" data-regla="entero:2000:2100">
                <?php foreach ($aniosDisponibles as $anioOpcion): ?>
                    <option value="<?= $anioOpcion ?>" <?= $anioOpcion === $anio ? 'selected' : '' ?>><?= $anioOpcion ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 campo">
            <label class="form-label" for="mes">Mes</label>
            <select class="form-select" id="mes" name="mes" data-regla="entero:0:12">
                <option value="0">Todos los meses</option>
                <?php for ($numeroMes = 1; $numeroMes <= 12; $numeroMes++): ?>
                    <option value="<?= $numeroMes ?>" <?= $numeroMes === $mes ? 'selected' : '' ?>><?= e(ucfirst(nombreMes($numeroMes))) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3 campo">
            <label class="form-label" for="dia">Día específico <span class="text-muted">(opcional)</span></label>
            <input class="form-control" type="date" id="dia" name="dia" value="<?= e($dia) ?>" max="<?= date('Y-m-d') ?>" data-regla="requerido" data-opcional>
        </div>
        <div class="col-md-2"><button type="submit" class="btn btn-dark w-100">Generar</button></div>
        <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?= e(url('admin/reportes.php')) ?>">Limpiar</a></div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3"><div class="card text-bg-warning"><div class="card-body"><small>Ventas totales</small><div class="fs-4 fw-bold"><?= e(formatoMoneda($resumen['total'])) ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><small class="text-muted">Pedidos</small><div class="fs-4 fw-bold"><?= (int) $resumen['pedidos'] ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><small class="text-muted">Unidades vendidas</small><div class="fs-4 fw-bold"><?= $unidades ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><small class="text-muted">Ticket promedio</small><div class="fs-4 fw-bold"><?= e(formatoMoneda($resumen['ticket_promedio'])) ?></div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Ventas por <?= $agruparPorDia ? 'día' : 'mes' ?></strong></div>
            <div class="card-body">
                <?php if (!$ventasAgrupadas): ?><p class="text-muted mb-0">No hay ventas en el periodo seleccionado.</p><?php endif; ?>
                <?php foreach ($ventasAgrupadas as $venta):
                    $porcentaje = $totalMaximo > 0 ? round($venta['total'] * 100 / $totalMaximo) : 0; ?>
                    <div class="d-flex align-items-center gap-2 mb-2 small">
                        <span style="width:110px"><?= e($agruparPorDia ? formatoFecha($venta['periodo']) : ucfirst(nombreMes((int) $venta['periodo']))) ?></span>
                        <div class="progress flex-grow-1" role="progressbar" aria-valuenow="<?= (int) $porcentaje ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-dark" style="width:<?= (int) $porcentaje ?>%"></div>
                        </div>
                        <span style="width:100px" class="text-end"><?= e(formatoMoneda($venta['total'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Ventas por tipo de producto (categoría)</strong></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Categoría</th><th>Unidades</th><th class="text-end">Importe</th></tr></thead>
                    <tbody>
                    <?php foreach ($ventasPorCategoria as $indice => $fila): ?>
                        <tr>
                            <td><?= e($fila['categoria']) ?><?= $indice === 0 ? ' <span class="badge text-bg-success">Más vendida</span>' : '' ?></td>
                            <td><?= (int) $fila['unidades'] ?></td>
                            <td class="text-end"><?= e(formatoMoneda($fila['importe'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ventasPorCategoria): ?><tr><td colspan="3" class="text-muted">Sin datos.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><strong>Tipo de producto más vendido por mes (<?= $anio ?>)</strong></div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead class="table-light"><tr><th>Mes</th><th>Categoría más vendida</th><th>Unidades</th><th>Importe de la categoría</th><th>Producto más vendido</th></tr></thead>
            <tbody>
            <?php for ($numeroMes = 1; $numeroMes <= 12; $numeroMes++): $fila = $masVendidoPorMes[$numeroMes] ?? null; ?>
                <tr>
                    <td><?= e(ucfirst(nombreMes($numeroMes))) ?></td>
                    <?php if ($fila): ?>
                        <td><strong><?= e($fila['categoria']) ?></strong></td>
                        <td><?= (int) $fila['unidades'] ?></td>
                        <td><?= e(formatoMoneda($fila['importe'])) ?></td>
                        <td><?= e($fila['producto'] ?? '') ?> <small class="text-muted">(<?= (int) ($fila['unidades_producto'] ?? 0) ?> u.)</small></td>
                    <?php else: ?>
                        <td colspan="4" class="text-muted">Sin ventas</td>
                    <?php endif; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Productos más vendidos en <?= e($descripcionPeriodo) ?></strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>#</th><th>SKU</th><th>Producto</th><th>Categoría</th><th>Unidades</th><th class="text-end">Importe</th></tr></thead>
            <tbody>
            <?php foreach ($productosMasVendidos as $indice => $fila): ?>
                <tr>
                    <td><?= $indice + 1 ?></td>
                    <td><?= e($fila['sku']) ?></td>
                    <td><?= e($fila['nombre']) ?></td>
                    <td><?= e($fila['categoria']) ?></td>
                    <td><?= (int) $fila['unidades'] ?></td>
                    <td class="text-end"><?= e(formatoMoneda($fila['importe'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$productosMasVendidos): ?><tr><td colspan="6" class="text-muted">No hay ventas en el periodo seleccionado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
