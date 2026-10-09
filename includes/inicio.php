<?php
/**
 * Archivo de arranque: se incluye al inicio de cada página.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/autenticacion.php';

// Cualquier error no controlado se registra y el usuario ve un mensaje genérico (sin datos de depuración)
set_exception_handler(function (Throwable $excepcion) {
    error_log('Excepción no controlada: ' . $excepcion->getMessage() . ' en ' . $excepcion->getFile() . ':' . $excepcion->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="es-MX"><head><meta charset="UTF-8"><title>Error | La Hormiga</title>'
        . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head><body>'
        . '<main class="container text-center py-5"><h1 class="h3">Ocurrió un problema</h1>'
        . '<p class="text-muted">No fue posible completar la operación. Inténtelo de nuevo más tarde.</p>'
        . '<a class="btn btn-primary" href="' . htmlspecialchars(url('/')) . '">Ir a la página de inicio</a></main></body></html>';
});

iniciarSesionSegura();
header('Content-Type: text/html; charset=UTF-8');
