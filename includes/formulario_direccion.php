<?php
/**
 * Campos del formulario de dirección. Requiere $valoresDireccion (array).
 */
$valoresDireccion = $valoresDireccion ?? [];
$paisesDisponibles = obtenerTodos('SELECT id, nombre FROM paises WHERE activo = 1 ORDER BY id');
$valorDireccion = function (string $clave) use ($valoresDireccion): string {
    return e($valoresDireccion[$clave] ?? '');
};
?>
<div class="row g-3 mb-3">
    <div class="col-12 campo">
        <label class="form-label" for="calle">Calle</label>
        <input class="form-control" type="text" id="calle" name="calle" value="<?= $valorDireccion('calle') ?>" maxlength="120" required data-regla="texto:120" autocomplete="address-line1">
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="numero_exterior">Número exterior</label>
        <input class="form-control" type="text" id="numero_exterior" name="numero_exterior" value="<?= $valorDireccion('numero_exterior') ?>" maxlength="10" required data-regla="texto:10">
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="numero_interior">Número interior <span class="text-muted">(opcional)</span></label>
        <input class="form-control" type="text" id="numero_interior" name="numero_interior" value="<?= $valorDireccion('numero_interior') ?>" maxlength="10" data-regla="texto:10" data-opcional>
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="codigo_postal">Código postal</label>
        <input class="form-control" type="text" id="codigo_postal" name="codigo_postal" value="<?= $valorDireccion('codigo_postal') ?>" maxlength="5" inputmode="numeric" required data-regla="cp" data-solo-digitos autocomplete="postal-code">
    </div>
    <div class="col-12 campo">
        <label class="form-label" for="colonia">Colonia</label>
        <input class="form-control" type="text" id="colonia" name="colonia" value="<?= $valorDireccion('colonia') ?>" maxlength="100" required data-regla="texto:100">
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="municipio">Municipio o alcaldía</label>
        <input class="form-control" type="text" id="municipio" name="municipio" value="<?= $valorDireccion('municipio') ?>" maxlength="100" required data-regla="texto:100">
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="ciudad">Ciudad</label>
        <input class="form-control" type="text" id="ciudad" name="ciudad" value="<?= $valorDireccion('ciudad') ?>" maxlength="100" required data-regla="texto:100" autocomplete="address-level2">
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="pais_id">País</label>
        <select class="form-select" id="pais_id" name="pais_id" required data-regla="seleccion">
            <?php foreach ($paisesDisponibles as $pais): ?>
                <option value="<?= (int) $pais['id'] ?>" <?= (string) ($valoresDireccion['pais_id'] ?? '1') === (string) $pais['id'] ? 'selected' : '' ?>><?= e($pais['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
