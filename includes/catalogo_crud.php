<?php
/**
 * CRUD genérico para los catálogos del sistema (categorías, países, tipos de tarjeta, estatus).
 * Requiere la variable $configuracionCatalogo con las claves:
 *   tabla, titulo, singular, seccion, campos[], tiene_activo (bool), orden (SQL), protegidos[] (ids)
 */
$usuario = requiereRol(ROL_ADMINISTRADOR);

$tablasPermitidas = ['categorias', 'paises', 'tipos_tarjeta', 'estatus_pedido'];
if (!in_array($configuracionCatalogo['tabla'], $tablasPermitidas, true)) {
    exit;
}
$tabla = $configuracionCatalogo['tabla'];
$campos = $configuracionCatalogo['campos'];
$tieneActivo = $configuracionCatalogo['tiene_activo'];
$protegidos = $configuracionCatalogo['protegidos'] ?? [];
$rutaPagina = $_SERVER['PHP_SELF'];
$rutaRelativa = 'admin/' . basename($rutaPagina);

$registroId = entradaEntero('id');
$registroEdicion = null;
$errores = [];

function validarCampoCatalogo(array $campo, string $valor): string
{
    if ($valor === '') {
        return $campo['requerido'] ? 'El campo «' . $campo['etiqueta'] . '» es obligatorio.' : '';
    }
    if ($campo['tipo'] === 'entero') {
        return validarEnteroRango($valor, 0, 255) ? '' : 'El campo «' . $campo['etiqueta'] . '» debe ser un número entero entre 0 y 255.';
    }
    return mb_strlen($valor, 'UTF-8') <= $campo['maximo'] ? '' : 'El campo «' . $campo['etiqueta'] . '» excede ' . $campo['maximo'] . ' caracteres.';
}

if (esPost()) {
    verificarCsrf();
    $accion = entrada('accion');

    if ($accion === 'guardar') {
        $valores = [];
        foreach ($campos as $campo) {
            $valor = entrada($campo['nombre']);
            $mensaje = validarCampoCatalogo($campo, $valor);
            if ($mensaje !== '') {
                $errores[] = $mensaje;
            }
            $valores[$campo['nombre']] = $valor === '' ? null : $valor;
        }
        if ($tieneActivo) {
            $valores['activo'] = entrada('activo') === '1' ? 1 : 0;
        }
        $duplicado = obtenerValor("SELECT 1 FROM $tabla WHERE nombre = ? AND id <> ?", [$valores['nombre'], $registroId]);
        if ($duplicado) {
            $errores[] = 'Ya existe un registro con el nombre «' . $valores['nombre'] . '».';
        }
        if (!$errores) {
            $columnas = array_keys($valores);
            if ($registroId > 0) {
                $asignaciones = implode(', ', array_map(function ($columna) { return "$columna = ?"; }, $columnas));
                consulta("UPDATE $tabla SET $asignaciones WHERE id = ?", array_merge(array_values($valores), [$registroId]));
                mensajeFlash('exito', 'El registro se actualizó correctamente.');
            } else {
                $marcadores = implode(', ', array_fill(0, count($columnas), '?'));
                consulta("INSERT INTO $tabla (" . implode(', ', $columnas) . ") VALUES ($marcadores)", array_values($valores));
                mensajeFlash('exito', 'El registro se creó correctamente.');
            }
            redirigir($rutaRelativa);
        }
        $registroEdicion = array_merge(['id' => $registroId], $valores);
    } elseif ($accion === 'eliminar') {
        if (in_array($registroId, $protegidos, true)) {
            mensajeFlash('error', 'Este registro es necesario para el funcionamiento del sistema y no puede eliminarse.');
        } else {
            try {
                consulta("DELETE FROM $tabla WHERE id = ?", [$registroId]);
                mensajeFlash('exito', 'El registro se eliminó correctamente.');
            } catch (PDOException $excepcion) {
                mensajeFlash('error', 'No se puede eliminar porque está en uso.' . ($tieneActivo ? ' Puede desactivarlo en su lugar.' : ''));
            }
        }
        redirigir($rutaRelativa);
    }
}

if ($registroEdicion === null && $registroId > 0) {
    $registroEdicion = obtenerUno("SELECT * FROM $tabla WHERE id = ?", [$registroId]);
}
$registros = obtenerTodos("SELECT * FROM $tabla ORDER BY " . $configuracionCatalogo['orden']);

$tituloPagina = $configuracionCatalogo['titulo'];
$plantilla = 'panel';
$seccionActiva = $configuracionCatalogo['seccion'];
require __DIR__ . '/encabezado.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= e($configuracionCatalogo['titulo']) ?></h1>
    <span class="text-muted">Catálogo &middot; <?= count($registros) ?> registros</span>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong><?= $registroEdicion ? 'Editar ' . e($configuracionCatalogo['singular']) : 'Nuevo registro' ?></strong></div>
            <div class="card-body">
                <?php foreach ($errores as $error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>
                <form method="post" action="<?= e(url($rutaRelativa)) ?>" data-validar <?= $registroEdicion ? 'data-confirmar="¿Desea guardar los cambios?"' : '' ?>>
                    <?= campoCsrf() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" value="<?= (int) ($registroEdicion['id'] ?? 0) ?>">
                    <?php foreach ($campos as $campo): ?>
                        <div class="mb-3 campo">
                            <label class="form-label" for="<?= e($campo['nombre']) ?>"><?= e($campo['etiqueta']) ?><?= $campo['requerido'] ? '' : ' <span class="text-muted">(opcional)</span>' ?></label>
                            <?php if ($campo['tipo'] === 'entero'): ?>
                                <input class="form-control" type="number" id="<?= e($campo['nombre']) ?>" name="<?= e($campo['nombre']) ?>" min="0" max="255" value="<?= e($registroEdicion[$campo['nombre']] ?? '') ?>" data-regla="entero:0:255" <?= $campo['requerido'] ? 'required' : 'data-opcional' ?>>
                            <?php else: ?>
                                <input class="form-control" type="text" id="<?= e($campo['nombre']) ?>" name="<?= e($campo['nombre']) ?>" maxlength="<?= (int) $campo['maximo'] ?>" value="<?= e($registroEdicion[$campo['nombre']] ?? '') ?>" data-regla="texto:<?= (int) $campo['maximo'] ?>" <?= $campo['requerido'] ? 'required' : 'data-opcional' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($tieneActivo): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= !isset($registroEdicion['activo']) || $registroEdicion['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Activo</label>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><?= $registroEdicion ? 'Guardar cambios' : 'Agregar' ?></button>
                    <?php if ($registroEdicion): ?><a class="btn btn-secondary" href="<?= e(url($rutaRelativa)) ?>">Cancelar</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <?php foreach ($campos as $campo): ?><th><?= e($campo['etiqueta']) ?></th><?php endforeach; ?>
                            <?php if ($tieneActivo): ?><th>Estado</th><?php endif; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registros as $registro): ?>
                        <tr class="<?= $tieneActivo && !$registro['activo'] ? 'table-secondary' : '' ?>">
                            <td><?= (int) $registro['id'] ?></td>
                            <?php foreach ($campos as $campo): ?><td><?= e($registro[$campo['nombre']] ?? '') ?></td><?php endforeach; ?>
                            <?php if ($tieneActivo): ?>
                                <td><?= $registro['activo'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="d-flex gap-1">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url($rutaRelativa . '?id=' . (int) $registro['id'])) ?>">Editar</a>
                                    <?php if (!in_array((int) $registro['id'], $protegidos, true)): ?>
                                        <form method="post" action="<?= e(url($rutaRelativa)) ?>" data-confirmar="¿Desea eliminar «<?= e($registro['nombre']) ?>»? Esta acción no se puede deshacer.">
                                            <?= campoCsrf() ?>
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int) $registro['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/pie.php'; ?>
