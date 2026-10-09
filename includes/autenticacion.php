<?php
/**
 * Manejo de sesiones y control de acceso por rol.
 */

const ROL_ADMINISTRADOR = 'administrador';
const ROL_CLIENTE       = 'cliente';
const ROL_INVENTARIOS   = 'inventarios';

const MINUTOS_INACTIVIDAD = 30;

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('LAHORMIGA_SESION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => URL_BASE === '' ? '/' : URL_BASE . '/',
        'secure'   => $esHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Expiración por inactividad
    $ahora = time();
    if (isset($_SESSION['ultima_actividad']) && ($ahora - $_SESSION['ultima_actividad']) > MINUTOS_INACTIVIDAD * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['mensajes'][] = ['tipo' => 'aviso', 'texto' => 'Su sesión expiró por inactividad. Inicie sesión nuevamente.'];
    }
    $_SESSION['ultima_actividad'] = $ahora;
}

function usuarioActual(bool $recargar = false): ?array
{
    static $usuario = false;
    if ($usuario !== false && !$recargar) {
        return $usuario;
    }
    $usuario = null;
    if (!empty($_SESSION['usuario_id'])) {
        $usuario = obtenerUno(
            'SELECT u.id, u.nombres, u.apellido_paterno, u.apellido_materno, u.email, r.clave AS rol, r.nombre AS rol_nombre
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ? AND u.activo = 1',
            [$_SESSION['usuario_id']]
        );
        if ($usuario === null) {
            unset($_SESSION['usuario_id']);
        }
    }
    return $usuario;
}

function iniciarSesionUsuario(int $usuarioId): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['ultima_actividad'] = time();
    usuarioActual(true);
}

function cerrarSesionUsuario(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
    }
    session_destroy();
}

function tieneRol(string ...$roles): bool
{
    $usuario = usuarioActual();
    return $usuario !== null && in_array($usuario['rol'], $roles, true);
}

function requiereSesion(): array
{
    $usuario = usuarioActual();
    if ($usuario === null) {
        $_SESSION['redirigir_despues'] = $_SERVER['REQUEST_URI'] ?? '';
        mensajeFlash('aviso', 'Inicie sesión para continuar.');
        redirigir('/login.php');
    }
    return $usuario;
}

function requiereRol(string ...$rolesPermitidos): array
{
    $usuario = requiereSesion();
    if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
        http_response_code(403);
        mensajeFlash('error', 'No tiene permisos para acceder a esa sección.');
        redirigir(rutaInicioPorRol($usuario['rol']));
    }
    return $usuario;
}

function rutaInicioPorRol(string $rol): string
{
    switch ($rol) {
        case ROL_ADMINISTRADOR:
            return '/admin/index.php';
        case ROL_INVENTARIOS:
            return '/inventario/productos.php';
        default:
            return '/tienda/catalogo.php';
    }
}
