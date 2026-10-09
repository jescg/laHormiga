<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
$usuario = requiereRol(ROL_INVENTARIOS, ROL_ADMINISTRADOR);

$estatusCatalogo = obtenerTodos('SELECT id, nombre FROM estatus_pedido ORDER BY orden, id');
$fechaConsulta = entrada('fecha', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaConsulta) || !strtotime($fechaConsulta)) {
    $fechaConsulta = date('Y-m-d');
}
$filtroEstatus = entradaEntero('estatus');

if (esPost()) {
    verificarCsrf();
    $pedidoId = entradaEntero('pedido_id');
    $nuevoEstatus = entradaEntero('estatus_id');
    $pedido = obtenerUno(
        'SELECT p.id, p.estatus_id, u.email, u.nombres FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?',
        [$pedidoId]
    );
    $estatusValido = obtenerUno('SELECT id, nombre FROM estatus_pedido WHERE id = ?', [$nuevoEstatus]);

    if ($pedido === null || $estatusValido === null) {
        mensajeFlash('error', 'El pedido o el estatus no son válidos.');
    } elseif ((int) $pedido['estatus_id'] === $nuevoEstatus) {
        mensajeFlash('aviso', 'El pedido ya tiene el estatus «' . $estatusValido['nombre'] . '».');
    } else {
        consulta('UPDATE pedidos SET estatus_id = ? WHERE id = ?', [$nuevoEstatus, $pedidoId]);
        consulta('INSERT INTO pedido_historial (pedido_id, estatus_id, usuario_id) VALUES (?, ?, ?)', [$pedidoId, $nuevoEstatus, $usuario['id']]);
        enviarCorreo(
            $pedido['email'],
            'Actualización de su pedido #' . $pedidoId,
            "Hola, {$pedido['nombres']}:\n\nSu pedido #{$pedidoId} cambió al estatus: {$estatusValido['nombre']}.\n\nLa Hormiga"
        );
        mensajeFlash('exito', 'El pedido n.º ' . $pedidoId . ' cambió a «' . $estatusValido['nombre'] . '».');
    }
    redirigir('/inventario/ventas.php?' . http_build_query(['fecha' => $fechaConsulta, 'estatus' => $filtroEstatus]));
}

$condiciones = ' WHERE DATE(p.fecha) = ?';
$parametros = [$fechaConsulta];
if ($filtroEstatus > 0) {
    $condiciones .= ' AND p.estatus_id = ?';
    $parametros[] = $filtroEstatus;
}
$pedidos = obtenerTodos(
    'SELECT p.id, p.fecha, p.total, p.estatus_id, p.fecha_entrega_estimada, e.nombre AS estatus,
            u.nombres, u.apellido_paterno, u.email, d.ciudad,
            (SELECT SUM(dt.cantidad) FROM pedido_detalle dt WHERE dt.pedido_id = p.id) AS unidades,
            (SELECT GROUP_CONCAT(CONCAT(dt.cantidad, " × ", pr.nombre) SEPARATOR "; ")
               FROM pedido_detalle dt JOIN productos pr ON pr.id = dt.producto_id WHERE dt.pedido_id = p.id) AS productos
       FROM pedidos p
       JOIN usuarios u ON u.id = p.usuario_id
       JOIN estatus_pedido e ON e.id = p.estatus_id
       JOIN direcciones d ON d.id = p.direccion_id' . $condiciones . '
      ORDER BY p.fecha DESC',
    $parametros
);
$resumenDia = obtenerUno(
    'SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS total FROM pedidos WHERE DATE(fecha) = ?',
    [$fechaConsulta]
);
$unidadesDia = (int) obtenerValor(
    'SELECT COALESCE(SUM(d.cantidad), 0) FROM pedido_detalle d JOIN pedidos p ON p.id = d.pedido_id WHERE DATE(p.fecha) = ?',
    [$fechaConsulta]
);
$pendientesEnvio = (int) obtenerValor('SELECT COUNT(*) FROM pedidos WHERE estatus_id IN (1, 2)');

$tituloPagina = 'Ventas del día';
$plantilla = 'panel';
$seccionActiva = 'ventas';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<h1 class="h3 mb-3">Ventas del <?= e(formatoFecha($fechaConsulta)) ?></h1>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3"><div class="card text-bg-warning"><div class="card-body"><small>Ventas del día</small><div class="fs-4 fw-bold"><?= e(formatoMoneda($resumenDia['total'])) ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><small class="text-muted">Pedidos</small><div class="fs-4 fw-bold"><?= (int) $resumenDia['pedidos'] ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><small class="text-muted">Unidades vendidas</small><div class="fs-4 fw-bold"><?= $unidadesDia ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body"><small class="text-muted">Pendientes de envío</small><div class="fs-4 fw-bold"><?= $pendientesEnvio ?></div></div></div></div>
</div>

<form class="card card-body mb-3" method="get" data-validar>
    <div class="row g-2 align-items-end">
        <div class="col-md-4 campo">
            <label class="form-label" for="fecha">Fecha</label>
            <input class="form-control" type="date" id="fecha" name="fecha" value="<?= e($fechaConsulta) ?>" max="<?= date('Y-m-d') ?>" required data-regla="requerido">
        </div>
        <div class="col-md-4 campo">
            <label class="form-label" for="estatus">Estatus</label>
            <select class="form-select" id="estatus" name="estatus" data-regla="entero:0:255">
                <option value="0">Todos</option>
                <?php foreach ($estatusCatalogo as $estatus): ?>
                    <option value="<?= (int) $estatus['id'] ?>" <?= $filtroEstatus === (int) $estatus['id'] ? 'selected' : '' ?>><?= e($estatus['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button type="submit" class="btn btn-dark w-100">Consultar</button></div>
        <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?= e(url('inventario/ventas.php')) ?>">Hoy</a></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Pedido</th><th>Hora</th><th>Cliente</th><th>Productos</th><th>Unidades</th><th>Total</th><th>Entrega</th><th>Estatus</th><th>Cambiar estatus</th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $pedido): ?>
                <tr>
                    <td>n.º <?= (int) $pedido['id'] ?></td>
                    <td><?= e(date('H:i', strtotime($pedido['fecha']))) ?></td>
                    <td><?= e($pedido['nombres'] . ' ' . $pedido['apellido_paterno']) ?><br><small class="text-muted"><?= e($pedido['ciudad']) ?></small></td>
                    <td class="small" style="max-width:260px"><?= e($pedido['productos']) ?></td>
                    <td><?= (int) $pedido['unidades'] ?></td>
                    <td><?= e(formatoMoneda($pedido['total'])) ?></td>
                    <td><?= e(formatoFecha($pedido['fecha_entrega_estimada'])) ?></td>
                    <td><span class="badge <?= claseEstatus((int) $pedido['estatus_id']) ?>"><?= e($pedido['estatus']) ?></span></td>
                    <td>
                        <form method="post" class="d-flex gap-1" data-validar data-confirmar="¿Desea cambiar el estatus del pedido n.º <?= (int) $pedido['id'] ?>?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                            <input type="hidden" name="fecha" value="<?= e($fechaConsulta) ?>">
                            <input type="hidden" name="estatus" value="<?= (int) $filtroEstatus ?>">
                            <div class="campo">
                                <select name="estatus_id" class="form-select form-select-sm" aria-label="Nuevo estatus" data-regla="seleccion">
                                    <?php foreach ($estatusCatalogo as $estatus): ?>
                                        <option value="<?= (int) $estatus['id'] ?>" <?= (int) $pedido['estatus_id'] === (int) $estatus['id'] ? 'selected' : '' ?>><?= e($estatus['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Aplicar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pedidos): ?><tr><td colspan="9" class="text-muted">No hay ventas registradas en esta fecha.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
