<?php
require_once __DIR__ . '/includes/inicio.php';
http_response_code(404);
$tituloPagina = 'Página no encontrada';
require __DIR__ . '/includes/encabezado.php';
?>
<div class="text-center py-5">
    <h1 class="display-6">Página no encontrada</h1>
    <p class="text-muted">No pudimos encontrar la página que busca.</p>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>">Ir al inicio</a>
</div>
<?php require __DIR__ . '/includes/pie.php'; ?>
