<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
require_once dirname(__DIR__) . '/includes/clientes.php';
$usuario = requiereRol(ROL_CLIENTE);
$usuarioId = (int) $usuario['id'];

$accion = entrada('accion', 'listar');
$volverACheckout = entrada('volver') === 'checkout';
$direccionId = entradaEntero('id');
$errores = [];
$valoresDireccion = ['pais_id' => '1'];

if ($direccionId > 0) {
    $direccionExistente = obtenerUno('SELECT * FROM direcciones WHERE id = ? AND usuario_id = ? AND activo = 1', [$direccionId, $usuarioId]);
    if ($direccionExistente === null) {
        mensajeFlash('error', 'La dirección solicitada no existe.');
        redirigir('/tienda/direcciones.php');
    }
    $valoresDireccion = $direccionExistente;
}

if (esPost()) {
    verificarCsrf();
    switch ($accion) {
        case 'guardar':
            $valoresDireccion = leerDatosDireccion();
            $errores = validarDatosDireccion($valoresDireccion);
            if (!$errores) {
                guardarDireccion($usuarioId, $valoresDireccion, entrada('es_principal') === '1', $direccionId > 0 ? $direccionId : null);
                mensajeFlash('exito', $direccionId > 0 ? 'La dirección se actualizó correctamente.' : 'La dirección se agregó correctamente.');
                redirigir($volverACheckout ? '/tienda/checkout.php' : '/tienda/direcciones.php');
            }
            $accion = $direccionId > 0 ? 'editar' : 'nueva';
            break;
        case 'principal':
            marcarDireccionPrincipal($usuarioId, $direccionId);
            mensajeFlash('exito', 'Se estableció la dirección principal.');
            redirigir('/tienda/direcciones.php');
            break;
        case 'eliminar':
            // Borrado lógico para conservar el historial de pedidos
            consulta('UPDATE direcciones SET activo = 0, es_principal = 0 WHERE id = ? AND usuario_id = ?', [$direccionId, $usuarioId]);
            reasignarPrincipal('direcciones', $usuarioId);
            mensajeFlash('exito', 'La dirección se eliminó.');
            redirigir('/tienda/direcciones.php');
            break;
    }
}

$direcciones = direccionesDelUsuario($usuarioId);
$tituloPagina = 'Mis direcciones';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('tienda/cuenta.php')) ?>">Mi cuenta</a></li><li class="breadcrumb-item active">Mis direcciones</li></ol></nav>

<?php if ($accion === 'nueva' || $accion === 'editar'): ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h1 class="h4 mb-3"><?= $direccionId > 0 ? 'Editar dirección' : 'Agregar dirección' ?></h1>
                    <?php if ($errores): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('tienda/direcciones.php')) ?>" data-validar <?= $direccionId > 0 ? 'data-confirmar="¿Desea guardar los cambios en esta dirección?"' : '' ?>>
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="guardar">
                        <input type="hidden" name="id" value="<?= (int) $direccionId ?>">
                        <?php if ($volverACheckout): ?><input type="hidden" name="volver" value="checkout"><?php endif; ?>
                        <?php require dirname(__DIR__) . '/includes/formulario_direccion.php'; ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="es_principal" name="es_principal" value="1" <?= !empty($valoresDireccion['es_principal']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="es_principal">Establecer como mi dirección principal</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar dirección</button>
                        <a class="btn btn-secondary" href="<?= e(url($volverACheckout ? 'tienda/checkout.php' : 'tienda/direcciones.php')) ?>">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Mis direcciones</h1>
        <a class="btn btn-primary" href="<?= e(url('tienda/direcciones.php?accion=nueva')) ?>"><i class="bi bi-plus-lg"></i> Agregar dirección</a>
    </div>
    <?php if (!$direcciones): ?><div class="alert alert-info">No tiene direcciones registradas.</div><?php endif; ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
        <?php foreach ($direcciones as $direccion): ?>
            <div class="col">
                <div class="card h-100 <?= $direccion['es_principal'] ? 'border-success' : '' ?>">
                    <div class="card-body">
                        <?php if ($direccion['es_principal']): ?><span class="badge text-bg-success mb-2">Principal</span><?php endif; ?>
                        <address class="mb-0">
                            <strong><?= e($usuario['nombres'] . ' ' . $usuario['apellido_paterno']) ?></strong><br>
                            <?= e($direccion['calle'] . ' ' . $direccion['numero_exterior'] . ($direccion['numero_interior'] ? ', int. ' . $direccion['numero_interior'] : '')) ?><br>
                            Col. <?= e($direccion['colonia']) ?>, C.P. <?= e($direccion['codigo_postal']) ?><br>
                            <?= e($direccion['municipio'] . ', ' . $direccion['ciudad']) ?><br>
                            <?= e($direccion['pais']) ?>
                        </address>
                    </div>
                    <div class="card-footer bg-white d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('tienda/direcciones.php?accion=editar&id=' . (int) $direccion['id'])) ?>">Editar</a>
                        <?php if (!$direccion['es_principal']): ?>
                            <form method="post" data-confirmar="¿Desea establecer esta dirección como principal?">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="accion" value="principal">
                                <input type="hidden" name="id" value="<?= (int) $direccion['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success">Hacer principal</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" data-confirmar="¿Desea eliminar esta dirección?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= (int) $direccion['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
