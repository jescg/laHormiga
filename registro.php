<?php
require_once __DIR__ . '/includes/inicio.php';
require_once __DIR__ . '/includes/clientes.php';

if (usuarioActual() !== null) {
    redirigir(rutaInicioPorRol(usuarioActual()['rol']));
}

$valoresUsuario = ['nombres' => '', 'apellido_paterno' => '', 'apellido_materno' => '', 'email' => '', 'telefono' => '', 'rfc' => '', 'curp' => ''];
$valoresDireccion = ['pais_id' => '1'];
$valoresTarjeta = [];
$errores = [];

if (esPost()) {
    verificarCsrf();
    $valoresUsuario = [
        'nombres'          => entrada('nombres'),
        'apellido_paterno' => entrada('apellido_paterno'),
        'apellido_materno' => entrada('apellido_materno'),
        'email'            => mb_strtolower(entrada('email'), 'UTF-8'),
        'telefono'         => entrada('telefono'),
        'rfc'              => strtoupper(entrada('rfc')),
        'curp'             => strtoupper(entrada('curp')),
    ];
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmacion = $_POST['confirmar_contrasena'] ?? '';
    $valoresDireccion = leerDatosDireccion();
    $valoresTarjeta = leerDatosTarjeta();

    if (!validarNombrePersona($valoresUsuario['nombres'])) {
        $errores[] = 'Escriba su nombre o nombres usando solo letras.';
    }
    if (!validarNombrePersona($valoresUsuario['apellido_paterno'])) {
        $errores[] = 'Escriba su apellido paterno usando solo letras.';
    }
    if (!validarNombrePersona($valoresUsuario['apellido_materno'])) {
        $errores[] = 'Escriba su apellido materno usando solo letras.';
    }
    if (!validarEmail($valoresUsuario['email'])) {
        $errores[] = 'El correo electrónico no tiene un formato válido.';
    } elseif (obtenerValor('SELECT 1 FROM usuarios WHERE email = ?', [$valoresUsuario['email']])) {
        $errores[] = 'Ya existe una cuenta con ese correo electrónico.';
    }
    if (!validarTelefono($valoresUsuario['telefono'])) {
        $errores[] = 'El teléfono debe tener 10 dígitos.';
    }
    if (!validarRfc($valoresUsuario['rfc'])) {
        $errores[] = 'El RFC no tiene un formato válido.';
    } elseif (obtenerValor('SELECT 1 FROM usuarios WHERE rfc = ?', [$valoresUsuario['rfc']])) {
        $errores[] = 'El RFC ya está registrado.';
    }
    if (!validarCurp($valoresUsuario['curp'])) {
        $errores[] = 'La CURP no tiene un formato válido.';
    } elseif (obtenerValor('SELECT 1 FROM usuarios WHERE curp = ?', [$valoresUsuario['curp']])) {
        $errores[] = 'La CURP ya está registrada.';
    }
    if (!is_string($contrasena) || !validarContrasena($contrasena)) {
        $errores[] = 'La contraseña debe tener de 8 a 64 caracteres, con mayúscula, minúscula, número y símbolo.';
    } elseif ($contrasena !== $confirmacion) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    $errores = array_merge($errores, validarDatosDireccion($valoresDireccion), validarDatosTarjeta($valoresTarjeta));

    if (!$errores) {
        $conexion = conexion();
        try {
            $conexion->beginTransaction();
            consulta(
                'INSERT INTO usuarios (rol_id, nombres, apellido_paterno, apellido_materno, email, contrasena_hash, rfc, curp, telefono)
                 VALUES ((SELECT id FROM roles WHERE clave = ?), ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    ROL_CLIENTE, $valoresUsuario['nombres'], $valoresUsuario['apellido_paterno'], $valoresUsuario['apellido_materno'],
                    $valoresUsuario['email'], password_hash($contrasena, PASSWORD_DEFAULT),
                    $valoresUsuario['rfc'], $valoresUsuario['curp'], $valoresUsuario['telefono'],
                ]
            );
            $nuevoUsuarioId = (int) $conexion->lastInsertId();
            guardarDireccion($nuevoUsuarioId, $valoresDireccion, true);
            guardarTarjeta($nuevoUsuarioId, $valoresTarjeta, true);
            $conexion->commit();

            iniciarSesionUsuario($nuevoUsuarioId);
            mensajeFlash('exito', '¡Su cuenta se creó correctamente! Ya puede comenzar a comprar.');
            redirigir('/tienda/catalogo.php');
        } catch (PDOException $excepcion) {
            $conexion->rollBack();
            error_log('Error en registro: ' . $excepcion->getMessage());
            $errores[] = 'No fue posible crear la cuenta. Inténtelo de nuevo.';
        }
    }
}

$tituloPagina = 'Crear cuenta';
require __DIR__ . '/includes/encabezado.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Crear cuenta</h1>
                <?php if ($errores): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Revise los siguientes datos:</strong>
                        <ul class="mb-0"><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="post" data-validar>
                    <?= campoCsrf() ?>
                    <h5 class="mt-2">Datos personales</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-12 campo">
                            <label class="form-label" for="nombres">Nombre(s)</label>
                            <input class="form-control" type="text" id="nombres" name="nombres" value="<?= e($valoresUsuario['nombres']) ?>" maxlength="80" required data-regla="nombre" autocomplete="given-name">
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="apellido_paterno">Apellido paterno</label>
                            <input class="form-control" type="text" id="apellido_paterno" name="apellido_paterno" value="<?= e($valoresUsuario['apellido_paterno']) ?>" maxlength="60" required data-regla="nombre">
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="apellido_materno">Apellido materno</label>
                            <input class="form-control" type="text" id="apellido_materno" name="apellido_materno" value="<?= e($valoresUsuario['apellido_materno']) ?>" maxlength="60" required data-regla="nombre">
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="rfc">RFC</label>
                            <input class="form-control" type="text" id="rfc" name="rfc" value="<?= e($valoresUsuario['rfc']) ?>" maxlength="13" required data-regla="rfc" data-mayusculas placeholder="HELC900515AB1">
                            <div class="form-text">13 caracteres, con homoclave.</div>
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="curp">CURP</label>
                            <input class="form-control" type="text" id="curp" name="curp" value="<?= e($valoresUsuario['curp']) ?>" maxlength="18" required data-regla="curp" data-mayusculas placeholder="HELC900515HDFRPR09">
                            <div class="form-text">18 caracteres.</div>
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="email">Correo electrónico</label>
                            <input class="form-control" type="email" id="email" name="email" value="<?= e($valoresUsuario['email']) ?>" maxlength="120" required data-regla="email" autocomplete="email">
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="telefono">Teléfono celular</label>
                            <input class="form-control" type="tel" id="telefono" name="telefono" value="<?= e($valoresUsuario['telefono']) ?>" maxlength="10" inputmode="numeric" required data-regla="telefono" data-solo-digitos autocomplete="tel">
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="contrasena">Contraseña</label>
                            <input class="form-control" type="password" id="contrasena" name="contrasena" maxlength="64" required data-regla="contrasena" autocomplete="new-password">
                            <div class="form-text">Mínimo 8 caracteres con mayúscula, minúscula, número y símbolo.</div>
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="confirmar_contrasena">Confirmar contraseña</label>
                            <input class="form-control" type="password" id="confirmar_contrasena" name="confirmar_contrasena" maxlength="64" required data-regla="confirmar:contrasena" autocomplete="new-password">
                        </div>
                    </div>

                    <hr>
                    <h5>Dirección de entrega principal</h5>
                    <p class="text-muted small">Podrá agregar más direcciones desde su cuenta.</p>
                    <?php require __DIR__ . '/includes/formulario_direccion.php'; ?>

                    <hr>
                    <h5>Método de pago principal</h5>
                    <p class="text-muted small">Podrá agregar más tarjetas desde su cuenta.</p>
                    <?php require __DIR__ . '/includes/formulario_tarjeta.php'; ?>

                    <button type="submit" class="btn btn-primary w-100">Crear mi cuenta</button>
                </form>
                <p class="mt-3 mb-0">¿Ya tiene una cuenta? <a href="<?= e(url('login.php')) ?>">Iniciar sesión</a></p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/pie.php'; ?>
