<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
$administrador = requiereRol(ROL_ADMINISTRADOR);

$roles = obtenerTodos('SELECT id, clave, nombre FROM roles ORDER BY id');
$accion = entrada('accion', 'listar');
$usuarioId = entradaEntero('id');
$errores = [];
$valores = ['rol_id' => '2', 'nombres' => '', 'apellido_paterno' => '', 'apellido_materno' => '', 'email' => '', 'telefono' => '', 'rfc' => '', 'curp' => '', 'activo' => 1];

if ($usuarioId > 0 && in_array($accion, ['editar', 'guardar'], true)) {
    $usuarioExistente = obtenerUno('SELECT * FROM usuarios WHERE id = ?', [$usuarioId]);
    if ($usuarioExistente === null) {
        mensajeFlash('error', 'El usuario no existe.');
        redirigir('/admin/usuarios.php');
    }
    $valores = $usuarioExistente;
}

if (esPost()) {
    verificarCsrf();

    if ($accion === 'guardar') {
        $valores = [
            'rol_id'           => entrada('rol_id'),
            'nombres'          => entrada('nombres'),
            'apellido_paterno' => entrada('apellido_paterno'),
            'apellido_materno' => entrada('apellido_materno'),
            'email'            => mb_strtolower(entrada('email'), 'UTF-8'),
            'telefono'         => entrada('telefono'),
            'rfc'              => strtoupper(entrada('rfc')),
            'curp'             => strtoupper(entrada('curp')),
            'activo'           => entrada('activo') === '1' ? 1 : 0,
        ];
        $contrasena = $_POST['contrasena'] ?? '';
        $rolSeleccionado = null;
        foreach ($roles as $rol) {
            if ((string) $rol['id'] === $valores['rol_id']) {
                $rolSeleccionado = $rol;
            }
        }
        $esCliente = $rolSeleccionado !== null && $rolSeleccionado['clave'] === ROL_CLIENTE;

        if ($rolSeleccionado === null) {
            $errores[] = 'Seleccione un tipo de usuario válido.';
        }
        if (!validarNombrePersona($valores['nombres']) || !validarNombrePersona($valores['apellido_paterno'])) {
            $errores[] = 'El nombre y el apellido paterno son obligatorios y solo admiten letras.';
        }
        if ($valores['apellido_materno'] !== '' && !validarNombrePersona($valores['apellido_materno'])) {
            $errores[] = 'El apellido materno solo admite letras.';
        }
        if (!validarEmail($valores['email'])) {
            $errores[] = 'El correo electrónico no es válido.';
        } elseif (obtenerValor('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?', [$valores['email'], $usuarioId])) {
            $errores[] = 'El correo electrónico ya está registrado.';
        }
        if ($valores['telefono'] !== '' && !validarTelefono($valores['telefono'])) {
            $errores[] = 'El teléfono debe tener 10 dígitos.';
        }
        if (($esCliente || $valores['rfc'] !== '') && !validarRfc($valores['rfc'])) {
            $errores[] = 'El RFC no es válido' . ($esCliente ? ' (obligatorio para clientes).' : '.');
        } elseif ($valores['rfc'] !== '' && obtenerValor('SELECT 1 FROM usuarios WHERE rfc = ? AND id <> ?', [$valores['rfc'], $usuarioId])) {
            $errores[] = 'El RFC ya está registrado.';
        }
        if (($esCliente || $valores['curp'] !== '') && !validarCurp($valores['curp'])) {
            $errores[] = 'La CURP no es válida' . ($esCliente ? ' (obligatoria para clientes).' : '.');
        } elseif ($valores['curp'] !== '' && obtenerValor('SELECT 1 FROM usuarios WHERE curp = ? AND id <> ?', [$valores['curp'], $usuarioId])) {
            $errores[] = 'La CURP ya está registrada.';
        }
        if (($usuarioId === 0 || $contrasena !== '') && (!is_string($contrasena) || !validarContrasena($contrasena))) {
            $errores[] = 'La contraseña debe tener de 8 a 64 caracteres, con mayúscula, minúscula, número y símbolo.';
        }
        if ($usuarioId === (int) $administrador['id'] && ($valores['activo'] !== 1 || ($rolSeleccionado['clave'] ?? '') !== ROL_ADMINISTRADOR)) {
            $errores[] = 'No puede desactivar su propia cuenta ni quitarse el rol de administrador.';
        }

        if (!$errores) {
            $parametros = [
                (int) $valores['rol_id'], $valores['nombres'], $valores['apellido_paterno'],
                $valores['apellido_materno'] !== '' ? $valores['apellido_materno'] : null, $valores['email'],
                $valores['telefono'] !== '' ? $valores['telefono'] : null,
                $valores['rfc'] !== '' ? $valores['rfc'] : null, $valores['curp'] !== '' ? $valores['curp'] : null, $valores['activo'],
            ];
            if ($usuarioId > 0) {
                consulta(
                    'UPDATE usuarios SET rol_id = ?, nombres = ?, apellido_paterno = ?, apellido_materno = ?, email = ?, telefono = ?, rfc = ?, curp = ?, activo = ? WHERE id = ?',
                    array_merge($parametros, [$usuarioId])
                );
                if ($contrasena !== '') {
                    consulta('UPDATE usuarios SET contrasena_hash = ? WHERE id = ?', [password_hash($contrasena, PASSWORD_DEFAULT), $usuarioId]);
                }
                mensajeFlash('exito', 'El usuario se actualizó correctamente.');
            } else {
                consulta(
                    'INSERT INTO usuarios (rol_id, nombres, apellido_paterno, apellido_materno, email, telefono, rfc, curp, activo, contrasena_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    array_merge($parametros, [password_hash($contrasena, PASSWORD_DEFAULT)])
                );
                mensajeFlash('exito', 'El usuario se creó correctamente.');
            }
            redirigir('/admin/usuarios.php');
        }
        $accion = $usuarioId > 0 ? 'editar' : 'nuevo';
    } elseif ($accion === 'eliminar') {
        if ($usuarioId === (int) $administrador['id']) {
            mensajeFlash('error', 'No puede eliminar su propia cuenta.');
        } else {
            try {
                consulta('DELETE FROM usuarios WHERE id = ?', [$usuarioId]);
                mensajeFlash('exito', 'El usuario se eliminó correctamente.');
            } catch (PDOException $excepcion) {
                mensajeFlash('error', 'El usuario tiene pedidos registrados y no puede eliminarse. Puede desactivarlo en su lugar.');
            }
        }
        redirigir('/admin/usuarios.php');
    }
}

$filtroRol = entradaEntero('rol');
$filtroBusqueda = mb_substr(entrada('buscar'), 0, 100, 'UTF-8');
$condiciones = ' WHERE 1 = 1';
$parametros = [];
if ($filtroRol > 0) {
    $condiciones .= ' AND u.rol_id = ?';
    $parametros[] = $filtroRol;
}
if ($filtroBusqueda !== '') {
    $condiciones .= " AND (CONCAT_WS(' ', u.nombres, u.apellido_paterno, u.apellido_materno) LIKE ? OR u.email LIKE ?)";
    $parametros[] = '%' . $filtroBusqueda . '%';
    $parametros[] = '%' . $filtroBusqueda . '%';
}
$usuarios = obtenerTodos(
    'SELECT u.id, u.nombres, u.apellido_paterno, u.apellido_materno, u.email, u.telefono, u.activo, u.creado_en, r.nombre AS rol
       FROM usuarios u JOIN roles r ON r.id = u.rol_id' . $condiciones . ' ORDER BY u.id DESC',
    $parametros
);

$tituloPagina = 'Usuarios';
$plantilla = 'panel';
$seccionActiva = 'usuarios';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= $usuarioId > 0 ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
        <a href="<?= e(url('admin/usuarios.php')) ?>">&larr; Volver a la lista</a>
    </div>
    <div class="card" style="max-width:860px">
        <div class="card-body">
            <?php if ($errores): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('admin/usuarios.php')) ?>" data-validar data-confirmar="¿Desea guardar este usuario?">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= (int) $usuarioId ?>">
                <div class="row g-3 mb-3">
                    <div class="col-md-6 campo">
                        <label class="form-label" for="rol_id">Tipo de usuario</label>
                        <select class="form-select" id="rol_id" name="rol_id" required data-regla="seleccion">
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= (int) $rol['id'] ?>" <?= (string) $valores['rol_id'] === (string) $rol['id'] ? 'selected' : '' ?>><?= e($rol['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 campo">
                        <label class="form-label" for="email">Correo electrónico</label>
                        <input class="form-control" type="email" id="email" name="email" value="<?= e($valores['email']) ?>" maxlength="120" required data-regla="email">
                    </div>
                    <div class="col-md-4 campo">
                        <label class="form-label" for="nombres">Nombre(s)</label>
                        <input class="form-control" type="text" id="nombres" name="nombres" value="<?= e($valores['nombres']) ?>" maxlength="80" required data-regla="nombre">
                    </div>
                    <div class="col-md-4 campo">
                        <label class="form-label" for="apellido_paterno">Apellido paterno</label>
                        <input class="form-control" type="text" id="apellido_paterno" name="apellido_paterno" value="<?= e($valores['apellido_paterno']) ?>" maxlength="60" required data-regla="nombre">
                    </div>
                    <div class="col-md-4 campo">
                        <label class="form-label" for="apellido_materno">Apellido materno</label>
                        <input class="form-control" type="text" id="apellido_materno" name="apellido_materno" value="<?= e($valores['apellido_materno']) ?>" maxlength="60" data-regla="nombre" data-opcional>
                    </div>
                    <div class="col-md-4 campo">
                        <label class="form-label" for="telefono">Teléfono <span class="text-muted">(opcional)</span></label>
                        <input class="form-control" type="tel" id="telefono" name="telefono" value="<?= e($valores['telefono']) ?>" maxlength="10" data-regla="telefono" data-opcional data-solo-digitos>
                    </div>
                    <div class="col-md-4 campo">
                        <label class="form-label" for="rfc">RFC</label>
                        <input class="form-control" type="text" id="rfc" name="rfc" value="<?= e($valores['rfc']) ?>" maxlength="13" data-regla="rfc" data-opcional data-mayusculas>
                        <div class="form-text">Obligatorio para clientes.</div>
                    </div>
                    <div class="col-md-4 campo">
                        <label class="form-label" for="curp">CURP</label>
                        <input class="form-control" type="text" id="curp" name="curp" value="<?= e($valores['curp']) ?>" maxlength="18" data-regla="curp" data-opcional data-mayusculas>
                        <div class="form-text">Obligatoria para clientes.</div>
                    </div>
                    <div class="col-12 campo">
                        <label class="form-label" for="contrasena">Contraseña <?= $usuarioId > 0 ? '<span class="text-muted">(déjela vacía para conservar la actual)</span>' : '' ?></label>
                        <input class="form-control" type="password" id="contrasena" name="contrasena" maxlength="64" data-regla="contrasena" <?= $usuarioId > 0 ? 'data-opcional' : 'required' ?> autocomplete="new-password">
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= $valores['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">Cuenta activa</label>
                </div>
                <button type="submit" class="btn btn-primary">Guardar usuario</button>
                <a class="btn btn-secondary" href="<?= e(url('admin/usuarios.php')) ?>">Cancelar</a>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Usuarios</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/usuarios.php?accion=nuevo')) ?>"><i class="bi bi-plus-lg"></i> Nuevo usuario</a>
    </div>
    <form class="card card-body mb-3" method="get" data-validar>
        <div class="row g-2 align-items-end">
            <div class="col-md-5 campo">
                <label class="form-label" for="buscar">Buscar</label>
                <input class="form-control" type="search" id="buscar" name="buscar" value="<?= e($filtroBusqueda) ?>" placeholder="Nombre o correo" data-regla="busqueda">
            </div>
            <div class="col-md-4 campo">
                <label class="form-label" for="rol">Tipo de usuario</label>
                <select class="form-select" id="rol" name="rol" data-regla="entero:0:99">
                    <option value="0">Todos</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?= (int) $rol['id'] ?>" <?= $filtroRol === (int) $rol['id'] ? 'selected' : '' ?>><?= e($rol['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><button type="submit" class="btn btn-dark w-100">Filtrar</button></div>
        </div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Tipo</th><th>Teléfono</th><th>Estado</th><th>Alta</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $fila): ?>
                    <tr class="<?= $fila['activo'] ? '' : 'table-secondary' ?>">
                        <td><?= (int) $fila['id'] ?></td>
                        <td><?= e(trim($fila['nombres'] . ' ' . $fila['apellido_paterno'] . ' ' . $fila['apellido_materno'])) ?></td>
                        <td><?= e($fila['email']) ?></td>
                        <td><span class="badge text-bg-light border"><?= e($fila['rol']) ?></span></td>
                        <td><?= e($fila['telefono']) ?></td>
                        <td><?= $fila['activo'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                        <td><?= e(formatoFecha($fila['creado_en'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/usuarios.php?accion=editar&id=' . (int) $fila['id'])) ?>">Editar</a>
                                <?php if ((int) $fila['id'] !== (int) $administrador['id']): ?>
                                    <form method="post" action="<?= e(url('admin/usuarios.php')) ?>" data-confirmar="¿Desea eliminar al usuario «<?= e($fila['email']) ?>»? Esta acción no se puede deshacer.">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int) $fila['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$usuarios): ?><tr><td colspan="8" class="text-muted">No se encontraron usuarios.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
