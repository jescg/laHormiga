<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
$usuario = requiereRol(ROL_CLIENTE);

$pedidos = obtenerTodos(
    'SELECT p.id, p.total, p.fecha, p.fecha_entrega_estimada, p.estatus_id, e.nombre AS estatus,
            d.calle, d.numero_exterior, d.ciudad
       FROM pedidos p
       JOIN estatus_pedido e ON e.id = p.estatus_id
       JOIN direcciones d ON d.id = p.direccion_id
      WHERE p.usuario_id = ?
      ORDER BY p.fecha DESC',
    [$usuario['id']]
);
$productosPorPedido = [];
if ($pedidos) {
    $identificadores = array_column($pedidos, 'id');
    $marcadores = implode(',', array_fill(0, count($identificadores), '?'));
    $filas = obtenerTodos(
        "SELECT d.pedido_id, d.producto_id, d.cantidad, pr.nombre,
                (SELECT i.ruta FROM producto_imagenes i WHERE i.producto_id = pr.id ORDER BY i.orden, i.id LIMIT 1) AS imagen
           FROM pedido_detalle d JOIN productos pr ON pr.id = d.producto_id
          WHERE d.pedido_id IN ($marcadores)",
        $identificadores
    );
    foreach ($filas as $fila) {
        $productosPorPedido[$fila['pedido_id']][] = $fila;
    }
}

$tituloPagina = 'Mis pedidos';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('tienda/cuenta.php')) ?>">Mi cuenta</a></li><li class="breadcrumb-item active">Mis pedidos</li></ol></nav>
<h1 class="h3 mb-3">Mis pedidos</h1>

<?php if (!$pedidos): ?>
    <div class="alert alert-info">Aún no ha realizado pedidos. <a class="alert-link" href="<?= e(url('tienda/catalogo.php')) ?>">Comenzar a comprar</a>.</div>
<?php endif; ?>

<?php foreach ($pedidos as $pedido): ?>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-4 small">
            <div><span class="text-muted">Fecha</span><br><strong><?= e(formatoFecha($pedido['fecha'])) ?></strong></div>
            <div><span class="text-muted">Total</span><br><strong><?= e(formatoMoneda($pedido['total'])) ?></strong></div>
            <div><span class="text-muted">Enviar a</span><br><strong><?= e($pedido['calle'] . ' ' . $pedido['numero_exterior'] . ', ' . $pedido['ciudad']) ?></strong></div>
            <div class="ms-auto text-end"><span class="text-muted">Pedido n.º <?= (int) $pedido['id'] ?></span><br><a href="<?= e(url('tienda/pedido.php?id=' . (int) $pedido['id'])) ?>">Ver detalle</a></div>
        </div>
        <div class="card-body">
            <p>
                <span class="badge <?= claseEstatus((int) $pedido['estatus_id']) ?>"><?= e($pedido['estatus']) ?></span>
                <?php if ((int) $pedido['estatus_id'] < 4): ?><small class="text-muted ms-2">Entrega estimada: <?= e(formatoFecha($pedido['fecha_entrega_estimada'])) ?></small><?php endif; ?>
            </p>
            <?php foreach ($productosPorPedido[$pedido['id']] ?? [] as $productoPedido): ?>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <img class="miniatura-grande rounded" src="<?= e(rutaImagen($productoPedido['imagen'])) ?>" alt="">
                    <div>
                        <a href="<?= e(url('tienda/producto.php?id=' . (int) $productoPedido['producto_id'])) ?>"><?= e($productoPedido['nombre']) ?></a><br>
                        <small class="text-muted">Cantidad: <?= (int) $productoPedido['cantidad'] ?></small><br>
                        <a class="btn btn-sm btn-outline-secondary mt-1" href="<?= e(url('tienda/producto.php?id=' . (int) $productoPedido['producto_id'] . '#opiniones')) ?>">Calificar y recomendar</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
