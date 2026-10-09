<?php
require_once __DIR__ . '/includes/inicio.php';

$tituloPagina = 'Compra en línea con envío a todo México';

$productosDestacados = obtenerTodos(
    SQL_PRODUCTOS_TIENDA . ' ORDER BY (SELECT COALESCE(SUM(d.cantidad), 0) FROM pedido_detalle d WHERE d.producto_id = p.id) DESC, p.id LIMIT 8'
);

$usuarioSesion = usuarioActual();

require __DIR__ . '/includes/encabezado.php';
?>

<section class="portada rounded-3 p-4 p-md-5 mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <h1 class="display-5 fw-bold">Bienvenido a La Hormiga</h1>
            <p class="lead">Tienda en línea de venta general de productos: electrónica, hogar, deportes, libros, moda y papelería. Compra de forma segura, paga con tarjeta y consulta el estatus de tu pedido hasta que llegue a tu casa.</p>
            <a class="btn btn-hormiga btn-lg me-2 mb-2" href="<?= e(url('tienda/catalogo.php')) ?>">Ver catálogo</a>
            <?php if ($usuarioSesion === null): ?>
                <a class="btn btn-outline-light btn-lg me-2 mb-2" href="<?= e(url('login.php')) ?>">Iniciar sesión</a>
                <a class="btn btn-outline-light btn-lg mb-2" href="<?= e(url('registro.php')) ?>">Crear cuenta</a>
            <?php endif; ?>
        </div>
        <div class="col-lg-5 d-none d-lg-block text-center">
            <img src="<?= e(url('assets/img/portada.svg')) ?>" alt="Paquetes listos para envío" class="img-fluid" width="380" height="300">
        </div>
    </div>
</section>

<section class="row g-3 mb-4" id="servicio">
    <div class="col-md-3"><div class="card h-100 text-center p-3"><i class="bi bi-truck fs-2 text-hormiga"></i><h6 class="mt-2">Envío a todo el país</h6><p class="small text-muted mb-0">Cada producto indica los días estimados de entrega.</p></div></div>
    <div class="col-md-3"><div class="card h-100 text-center p-3"><i class="bi bi-shield-check fs-2 text-hormiga"></i><h6 class="mt-2">Compra protegida</h6><p class="small text-muted mb-0">Nunca almacenamos el CVV de tu tarjeta.</p></div></div>
    <div class="col-md-3"><div class="card h-100 text-center p-3"><i class="bi bi-credit-card fs-2 text-hormiga"></i><h6 class="mt-2">Pago con tarjeta</h6><p class="small text-muted mb-0">Visa, Mastercard y American Express.</p></div></div>
    <div class="col-md-3"><div class="card h-100 text-center p-3"><i class="bi bi-star fs-2 text-hormiga"></i><h6 class="mt-2">Opiniones reales</h6><p class="small text-muted mb-0">Solo quienes compran pueden calificar.</p></div></div>
</section>

<section class="mb-4">
    <h2 class="h4 mb-3">Categorías</h2>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3">
        <?php foreach (categoriasActivas() as $categoria): ?>
            <div class="col">
                <a class="card text-center p-3 h-100 link-dark link-underline-opacity-0" href="<?= e(url('tienda/catalogo.php?categoria=' . (int) $categoria['id'])) ?>">
                    <i class="bi bi-tag fs-4 text-hormiga"></i>
                    <span class="small fw-semibold mt-1"><?= e($categoria['nombre']) ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Los más vendidos</h2>
        <a href="<?= e(url('tienda/catalogo.php')) ?>">Ver todo</a>
    </div>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
        <?php foreach ($productosDestacados as $producto): ?>
            <?= tarjetaProductoHtml($producto) ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="row g-3" id="nosotros">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5">¿Qué es La Hormiga?</h2>
            <p>Somos un mercado en línea de venta general de productos. Como las hormigas, trabajamos en equipo para llevar cada artículo desde nuestro almacén hasta tu domicilio.</p>
            <p class="mb-0">Te informamos el estatus de tu pedido en cada etapa: <strong>en proceso, pagada, enviado y recibido</strong>.</p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5">¿Cómo comprar?</h2>
            <ol class="mb-0">
                <li>Crea tu cuenta con tu nombre, RFC y CURP.</li>
                <li>Registra tus direcciones y tarjetas, y elige las principales.</li>
                <li>Agrega productos a tu carrito.</li>
                <li>Confirma tu pago y sigue tu pedido.</li>
            </ol>
        </div></div>
    </div>
</section>

<?php require __DIR__ . '/includes/pie.php'; ?>
