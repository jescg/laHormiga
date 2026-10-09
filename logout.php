<?php
require_once __DIR__ . '/includes/inicio.php';

if (!esPost()) {
    redirigir('/');
}
verificarCsrf();
cerrarSesionUsuario();

iniciarSesionSegura();
mensajeFlash('exito', 'Cerró su sesión correctamente.');
redirigir('/login.php');
