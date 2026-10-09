<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
$usuario = requiereRol(ROL_CLIENTE);
$usuarioId = (int) $usuario['id'];

$datos = obtenerUno('SELECT nombres, apellido_paterno, apellido_materno, email, telefono, rfc, curp, contrasena_hash FROM usuarios WHERE id = ?', [$usuarioId]);
$errores = [];

if (esPost()) {
    verificarCsrf();
    $accion = entrada('accion');

    if ($accion === 'perfil') {
        $nuevosDatos = [
            'nombres'          => entrada('nombres'),
            'apellido_paterno' => entrada('apellido_paterno'),
            'apellido_materno' => entrada('apellido_materno'),
            'telefono'         => entrada('telefono'),
        ];
        if (!validarNombrePersona($nuevosDatos['nombres']) || !validarNombrePersona($nuevosDatos['apellido_paterno']) || !validarNombrePersona($nuevosDatos['apellido_materno'])) {
            $errores[] = 'El nombre y los apellidos solo admiten letras y espacios.';
        }
        if (!validarTelefono($nuevosDatos['telefono'])) {
            $errores[] = 'El teléfono debe tener 10 dígitos.';
        }
        if (!$errores) {
            consulta(
                'UPDATE usuarios SET nombres = ?, apellido_paterno = ?, apellido_materno = ?, telefono = ? WHERE id = ?',
                [$nuevosDatos['nombres'], $nuevosDatos['apellido_paterno'], $nuevosDatos['apellido_materno'], $nuevosDatos['telefono'], $usuarioId]
            );
            mensajeFlash('exito', 'Sus datos se actualizaron correctamente.');
            redirigir('/tienda/cuenta.php');
        }
        $datos = array_merge($datos, $nuevosDatos);
    } elseif ($accion === 'contrasena') {
        $contrasenaActual = $_POST['contrasena_actual'] ?? '';
        $contrasenaNueva = $_POST['contrasena_nueva'] ?? '';
        if (!is_string($contrasenaActual) || !password_verify($contrasenaActual, $datos['contrasena_hash'])) {
            $errores[] = 'La contraseña actual es incorrecta.';
        } elseif (!is_string($contrasenaNueva) || !validarContrasena($contrasenaNueva)) {
            $errores[] = 'La nueva contraseña debe tener de 8 a 64 caracteres, con mayúscula, minúscula, número y símbolo.';
        } elseif ($contrasenaNueva !== ($_POST['confirmar_contrasena'] ?? '')) {
            $errores[] = 'Las contraseñas nuevas no coinciden.';
        } else {
            consulta('UPDATE usuarios SET contrasena_hash = ? WHERE id = ?', [password_hash($contrasenaNueva, PASSWORD_DEFAULT), $usuarioId]);
            session_regenerate_id(true);
            mensajeFlash('exito', 'Su contraseña se actualizó correctamente.');
            redirigir('/tienda/cuenta.php');
        }
    }
}

$tituloPagina = 'Mi cuenta';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<h1 class="h3 mb-3">Mi cuenta</h1>
<?php foreach ($errores as $error): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a class="card h-100 p-3 link-dark link-underline-opacity-0" href="<?= e(url('tienda/pedidos.php')) ?>">
            <h5><i class="bi bi-box-seam text-hormiga"></i> Mis pedidos</h5>
            <p class="small text-muted mb-0">Consultar el estatus de mis pedidos y calificar productos.</p>
        </a>
    </div>
    <div class="col-md-4">
        <a class="card h-100 p-3 link-dark link-underline-opacity-0" href="<?= e(url('tienda/direcciones.php')) ?>">
            <h5><i class="bi bi-geo-alt text-hormiga"></i> Mis direcciones</h5>
            <p class="small text-muted mb-0">Agregar, editar y elegir la dirección principal.</p>
        </a>
    </div>
    <div class="col-md-4">
        <a class="card h-100 p-3 link-dark link-underline-opacity-0" href="<?= e(url('tienda/metodos_pago.php')) ?>">
            <h5><i class="bi bi-credit-card text-hormiga"></i> Métodos de pago</h5>
            <p class="small text-muted mb-0">Administrar mis tarjetas y la tarjeta principal.</p>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><strong>Datos personales</strong></div>
            <div class="card-body">
                <form method="post" data-validar data-confirmar="¿Desea guardar los cambios en sus datos personales?">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="perfil">
                    <div class="mb-3 campo">
                        <label class="form-label" for="nombres">Nombre(s)</label>
                        <input class="form-control" type="text" id="nombres" name="nombres" value="<?= e($datos['nombres']) ?>" maxlength="80" required data-regla="nombre">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 campo">
                            <label class="form-label" for="apellido_paterno">Apellido paterno</label>
                            <input class="form-control" type="text" id="apellido_paterno" name="apellido_paterno" value="<?= e($datos['apellido_paterno']) ?>" maxlength="60" required data-regla="nombre">
                        </div>
                        <div class="col-md-6 campo">
                            <label class="form-label" for="apellido_materno">Apellido materno</label>
                            <input class="form-control" type="text" id="apellido_materno" name="apellido_materno" value="<?= e($datos['apellido_materno']) ?>" maxlength="60" required data-regla="nombre">
                        </div>
                    </div>
                    <div class="mb-3 campo">
                        <label class="form-label" for="telefono">Teléfono</label>
                        <input class="form-control" type="tel" id="telefono" name="telefono" value="<?= e($datos['telefono']) ?>" maxlength="10" required data-regla="telefono" data-solo-digitos>
                    </div>
                    <p class="small text-muted">Correo: <?= e($datos['email']) ?><br>RFC: <?= e($datos['rfc']) ?> &middot; CURP: <?= e($datos['curp']) ?></p>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><strong>Cambiar contraseña</strong></div>
            <div class="card-body">
                <form method="post" data-validar data-confirmar="¿Desea cambiar su contraseña?">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="contrasena">
                    <div class="mb-3 campo">
                        <label class="form-label" for="contrasena_actual">Contraseña actual</label>
                        <input class="form-control" type="password" id="contrasena_actual" name="contrasena_actual" required data-regla="requerido" autocomplete="current-password">
                    </div>
                    <div class="mb-3 campo">
                        <label class="form-label" for="contrasena_nueva">Nueva contraseña</label>
                        <input class="form-control" type="password" id="contrasena_nueva" name="contrasena_nueva" maxlength="64" required data-regla="contrasena" autocomplete="new-password">
                    </div>
                    <div class="mb-3 campo">
                        <label class="form-label" for="confirmar_contrasena">Confirmar nueva contraseña</label>
                        <input class="form-control" type="password" id="confirmar_contrasena" name="confirmar_contrasena" maxlength="64" required data-regla="confirmar:contrasena_nueva" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Actualizar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
