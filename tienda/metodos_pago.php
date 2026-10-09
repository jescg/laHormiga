<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
require_once dirname(__DIR__) . '/includes/clientes.php';
$usuario = requiereRol(ROL_CLIENTE);
$usuarioId = (int) $usuario['id'];

$accion = entrada('accion', 'listar');
$volverACheckout = entrada('volver') === 'checkout';
$tarjetaId = entradaEntero('id');
$errores = [];
$valoresTarjeta = ['titular' => mb_strtoupper($usuario['nombres'] . ' ' . $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno'], 'UTF-8')];

if (esPost()) {
    verificarCsrf();
    switch ($accion) {
        case 'guardar':
            $valoresTarjeta = leerDatosTarjeta();
            $errores = validarDatosTarjeta($valoresTarjeta);
            if (!$errores) {
                guardarTarjeta($usuarioId, $valoresTarjeta, entrada('es_principal') === '1');
                mensajeFlash('exito', 'La tarjeta se agregó correctamente.');
                redirigir($volverACheckout ? '/tienda/checkout.php' : '/tienda/metodos_pago.php');
            }
            $accion = 'nueva';
            break;
        case 'principal':
            if (obtenerValor('SELECT 1 FROM metodos_pago WHERE id = ? AND usuario_id = ? AND activo = 1', [$tarjetaId, $usuarioId])) {
                marcarTarjetaPrincipal($usuarioId, $tarjetaId);
                mensajeFlash('exito', 'Se estableció el método de pago principal.');
            }
            redirigir('/tienda/metodos_pago.php');
            break;
        case 'eliminar':
            consulta('UPDATE metodos_pago SET activo = 0, es_principal = 0 WHERE id = ? AND usuario_id = ?', [$tarjetaId, $usuarioId]);
            reasignarPrincipal('metodos_pago', $usuarioId);
            mensajeFlash('exito', 'La tarjeta se eliminó.');
            redirigir('/tienda/metodos_pago.php');
            break;
    }
}

$tarjetas = tarjetasDelUsuario($usuarioId);
$tituloPagina = 'Métodos de pago';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('tienda/cuenta.php')) ?>">Mi cuenta</a></li><li class="breadcrumb-item active">Métodos de pago</li></ol></nav>

<?php if ($accion === 'nueva'): ?>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h1 class="h4 mb-3">Agregar tarjeta de crédito o débito</h1>
                    <?php if ($errores): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('tienda/metodos_pago.php')) ?>" data-validar>
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="guardar">
                        <?php if ($volverACheckout): ?><input type="hidden" name="volver" value="checkout"><?php endif; ?>
                        <?php require dirname(__DIR__) . '/includes/formulario_tarjeta.php'; ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="es_principal" name="es_principal" value="1">
                            <label class="form-check-label" for="es_principal">Establecer como mi método de pago principal</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Agregar tarjeta</button>
                        <a class="btn btn-secondary" href="<?= e(url($volverACheckout ? 'tienda/checkout.php' : 'tienda/metodos_pago.php')) ?>">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Métodos de pago</h1>
        <a class="btn btn-primary" href="<?= e(url('tienda/metodos_pago.php?accion=nueva')) ?>"><i class="bi bi-plus-lg"></i> Agregar tarjeta</a>
    </div>
    <?php if (!$tarjetas): ?><div class="alert alert-info">No tiene tarjetas registradas.</div><?php endif; ?>
    <div class="list-group">
        <?php foreach ($tarjetas as $tarjeta): $vencida = tarjetaVencida($tarjeta); ?>
            <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <i class="bi bi-credit-card"></i> <strong><?= e($tarjeta['tipo']) ?></strong> terminación <strong><?= e($tarjeta['ultimos_digitos']) ?></strong>
                    <?php if ($tarjeta['es_principal']): ?><span class="badge text-bg-success">Principal</span><?php endif; ?>
                    <?php if ($vencida): ?><span class="badge text-bg-danger">Vencida</span><?php endif; ?><br>
                    <small class="text-muted"><?= e($tarjeta['titular']) ?> &middot; Vence <?= str_pad((string) $tarjeta['mes_vencimiento'], 2, '0', STR_PAD_LEFT) ?>/<?= (int) $tarjeta['anio_vencimiento'] ?></small>
                </div>
                <div class="d-flex gap-2">
                    <?php if (!$tarjeta['es_principal'] && !$vencida): ?>
                        <form method="post" data-confirmar="¿Desea establecer esta tarjeta como su método de pago principal?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="principal">
                            <input type="hidden" name="id" value="<?= (int) $tarjeta['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success">Hacer principal</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" data-confirmar="¿Desea eliminar la tarjeta con terminación <?= e($tarjeta['ultimos_digitos']) ?>?">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= (int) $tarjeta['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
