<?php
require_once __DIR__ . '/includes/inicio.php';

if (usuarioActual() !== null) {
    redirigir(rutaInicioPorRol(usuarioActual()['rol']));
}

const MAXIMO_INTENTOS = 5;
const MINUTOS_BLOQUEO = 10;

$email = '';
$errores = [];

if (esPost()) {
    verificarCsrf();
    $email = mb_strtolower(entrada('email'), 'UTF-8');
    $contrasena = $_POST['contrasena'] ?? '';
    $intentos = $_SESSION['intentos_acceso'] ?? ['cantidad' => 0, 'desde' => time()];

    if ($intentos['cantidad'] >= MAXIMO_INTENTOS && (time() - $intentos['desde']) < MINUTOS_BLOQUEO * 60) {
        $errores[] = 'Demasiados intentos fallidos. Espere ' . MINUTOS_BLOQUEO . ' minutos e inténtelo de nuevo.';
    } elseif (!validarEmail($email) || !is_string($contrasena) || $contrasena === '') {
        $errores[] = 'Escriba su correo electrónico y su contraseña.';
    } else {
        $usuario = obtenerUno('SELECT id, contrasena_hash, activo FROM usuarios WHERE email = ?', [$email]);
        if ($usuario !== null && password_verify($contrasena, $usuario['contrasena_hash'])) {
            if ((int) $usuario['activo'] !== 1) {
                $errores[] = 'Su cuenta está desactivada. Comuníquese con el administrador.';
            } else {
                unset($_SESSION['intentos_acceso']);
                if (password_needs_rehash($usuario['contrasena_hash'], PASSWORD_DEFAULT)) {
                    consulta('UPDATE usuarios SET contrasena_hash = ? WHERE id = ?', [password_hash($contrasena, PASSWORD_DEFAULT), $usuario['id']]);
                }
                $destino = $_SESSION['redirigir_despues'] ?? '';
                unset($_SESSION['redirigir_despues']);
                iniciarSesionUsuario((int) $usuario['id']);
                $usuarioSesion = usuarioActual();
                mensajeFlash('exito', 'Bienvenido(a), ' . $usuarioSesion['nombres'] . '.');
                $esDestinoInterno = $destino !== '' && strpos($destino, '//') === false && $destino[0] === '/';
                if ($esDestinoInterno) {
                    header('Location: ' . $destino);
                    exit;
                }
                redirigir(rutaInicioPorRol($usuarioSesion['rol']));
            }
        } else {
            if ((time() - $intentos['desde']) >= MINUTOS_BLOQUEO * 60) {
                $intentos = ['cantidad' => 0, 'desde' => time()];
            }
            $intentos['cantidad']++;
            $_SESSION['intentos_acceso'] = $intentos;
            $errores[] = 'El correo electrónico o la contraseña son incorrectos.';
        }
    }
}

$tituloPagina = 'Iniciar sesión';
require __DIR__ . '/includes/encabezado.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3 text-center">Iniciar sesión</h1>
                <?php foreach ($errores as $error): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" data-validar>
                    <?= campoCsrf() ?>
                    <div class="mb-3 campo">
                        <label class="form-label" for="email">Correo electrónico</label>
                        <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required data-regla="email" maxlength="120">
                    </div>
                    <div class="mb-3 campo">
                        <label class="form-label" for="contrasena">Contraseña</label>
                        <input class="form-control" type="password" id="contrasena" name="contrasena" autocomplete="current-password" required data-regla="requerido" maxlength="64">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
                <p class="text-center mt-3 mb-0">¿No tiene cuenta? <a href="<?= e(url('registro.php')) ?>">Regístrese</a></p>
            </div>
        </div>
        <div class="alert alert-secondary small mt-3">
            <strong>Cuentas de demostración</strong> (contraseña <code>Hormiga#2026</code>):<br>
            Administrador: <code>admin@lahormiga.mx</code><br>
            Cliente: <code>cliente@lahormiga.mx</code><br>
            Inventarios: <code>inventario@lahormiga.mx</code>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/pie.php'; ?>
