<?php
require_once dirname(__DIR__) . '/includes/inicio.php';
requiereRol(ROL_ADMINISTRADOR);

if (esPost()) {
    verificarCsrf();
    $accion = entrada('accion');
    $registroId = entradaEntero('id');

    if ($accion === 'eliminar_calificacion') {
        consulta('DELETE FROM calificaciones WHERE id = ?', [$registroId]);
        mensajeFlash('exito', 'La calificación se eliminó.');
    } elseif ($accion === 'editar_calificacion') {
        $estrellas = entrada('estrellas');
        if (validarEnteroRango($estrellas, 0, 5)) {
            consulta('UPDATE calificaciones SET estrellas = ? WHERE id = ?', [(int) $estrellas, $registroId]);
            mensajeFlash('exito', 'La calificación se actualizó.');
        } else {
            mensajeFlash('error', 'La calificación debe estar entre 0 y 5 estrellas.');
        }
    } elseif ($accion === 'eliminar_recomendacion') {
        consulta('DELETE FROM recomendaciones WHERE id = ?', [$registroId]);
        mensajeFlash('exito', 'La recomendación se eliminó.');
    } elseif ($accion === 'editar_recomendacion') {
        $comentario = entrada('comentario');
        if (mb_strlen($comentario, 'UTF-8') >= 10 && mb_strlen($comentario, 'UTF-8') <= 1000) {
            consulta('UPDATE recomendaciones SET comentario = ? WHERE id = ?', [$comentario, $registroId]);
            mensajeFlash('exito', 'La recomendación se actualizó.');
        } else {
            mensajeFlash('error', 'La recomendación debe tener entre 10 y 1000 caracteres.');
        }
    }
    redirigir('/admin/resenas.php');
}

$calificaciones = obtenerTodos(
    'SELECT ca.id, ca.estrellas, ca.fecha, pr.nombre AS producto, pr.id AS producto_id, u.nombres, u.apellido_paterno
       FROM calificaciones ca JOIN productos pr ON pr.id = ca.producto_id JOIN usuarios u ON u.id = ca.usuario_id
      ORDER BY ca.fecha DESC'
);
$recomendaciones = obtenerTodos(
    'SELECT r.id, r.comentario, r.fecha, pr.nombre AS producto, pr.id AS producto_id, u.nombres, u.apellido_paterno
       FROM recomendaciones r JOIN productos pr ON pr.id = r.producto_id JOIN usuarios u ON u.id = r.usuario_id
      ORDER BY r.fecha DESC'
);

$tituloPagina = 'Calificaciones y recomendaciones';
$plantilla = 'panel';
$seccionActiva = 'resenas';
require dirname(__DIR__) . '/includes/encabezado.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Calificaciones y recomendaciones</h1>
    <small class="text-muted">Los clientes solo pueden opinar sobre productos que compraron.</small>
</div>

<div class="card mb-4">
    <div class="card-header"><strong>Calificaciones (<?= count($calificaciones) ?>)</strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Producto</th><th>Cliente</th><th>Fecha</th><th>Calificación</th><th>Modificar</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($calificaciones as $calificacion): ?>
                <tr>
                    <td><a href="<?= e(url('tienda/producto.php?id=' . (int) $calificacion['producto_id'])) ?>"><?= e($calificacion['producto']) ?></a></td>
                    <td><?= e($calificacion['nombres'] . ' ' . $calificacion['apellido_paterno']) ?></td>
                    <td><?= e(formatoFecha($calificacion['fecha'])) ?></td>
                    <td><?= estrellasHtml((float) $calificacion['estrellas']) ?></td>
                    <td>
                        <form method="post" class="d-flex gap-1" data-validar data-confirmar="¿Desea modificar esta calificación?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="editar_calificacion">
                            <input type="hidden" name="id" value="<?= (int) $calificacion['id'] ?>">
                            <div class="campo">
                                <select name="estrellas" class="form-select form-select-sm" aria-label="Estrellas" data-regla="estrellas">
                                    <?php for ($estrellas = 0; $estrellas <= 5; $estrellas++): ?>
                                        <option value="<?= $estrellas ?>" <?= (int) $calificacion['estrellas'] === $estrellas ? 'selected' : '' ?>><?= $estrellas ?> &#9733;</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" data-confirmar="¿Desea eliminar esta calificación?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="eliminar_calificacion">
                            <input type="hidden" name="id" value="<?= (int) $calificacion['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$calificaciones): ?><tr><td colspan="6" class="text-muted">Sin calificaciones.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Recomendaciones (<?= count($recomendaciones) ?>)</strong></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Producto</th><th>Cliente</th><th>Fecha</th><th>Recomendación</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recomendaciones as $recomendacion): ?>
                <tr>
                    <td><a href="<?= e(url('tienda/producto.php?id=' . (int) $recomendacion['producto_id'])) ?>"><?= e($recomendacion['producto']) ?></a></td>
                    <td><?= e($recomendacion['nombres'] . ' ' . $recomendacion['apellido_paterno']) ?></td>
                    <td><?= e(formatoFecha($recomendacion['fecha'])) ?></td>
                    <td style="min-width:320px">
                        <form method="post" data-validar data-confirmar="¿Desea guardar los cambios en esta recomendación?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="editar_recomendacion">
                            <input type="hidden" name="id" value="<?= (int) $recomendacion['id'] ?>">
                            <div class="campo mb-1">
                                <textarea class="form-control form-control-sm" name="comentario" rows="2" maxlength="1000" aria-label="Recomendación" data-regla="texto:1000"><?= e($recomendacion['comentario']) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" data-confirmar="¿Desea eliminar esta recomendación?">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="eliminar_recomendacion">
                            <input type="hidden" name="id" value="<?= (int) $recomendacion['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recomendaciones): ?><tr><td colspan="5" class="text-muted">Sin recomendaciones.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/pie.php'; ?>
