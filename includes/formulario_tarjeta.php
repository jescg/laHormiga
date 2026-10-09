<?php
/**
 * Campos del formulario de tarjeta. Requiere $valoresTarjeta (array).
 * El número completo y el CVV nunca se vuelven a mostrar ni se guardan.
 */
$valoresTarjeta = $valoresTarjeta ?? [];
$anioActual = (int) date('Y');
?>
<div class="row g-3 mb-2">
    <div class="col-12 campo">
        <label class="form-label" for="titular">Nombre del titular</label>
        <input class="form-control" type="text" id="titular" name="titular" value="<?= e($valoresTarjeta['titular'] ?? '') ?>" maxlength="120" required data-regla="nombre" data-mayusculas autocomplete="cc-name">
    </div>
    <div class="col-12 campo">
        <label class="form-label" for="numero_tarjeta">Número de tarjeta <span class="badge text-bg-light" id="marcaTarjeta">Tarjeta</span></label>
        <input class="form-control" type="text" id="numero_tarjeta" name="numero_tarjeta" maxlength="23" inputmode="numeric" required data-regla="tarjeta" data-formato-tarjeta="marcaTarjeta" autocomplete="cc-number" placeholder="0000 0000 0000 0000">
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="mes_vencimiento">Mes de vencimiento</label>
        <select class="form-select" id="mes_vencimiento" name="mes_vencimiento" required data-regla="mes">
            <option value="">Mes</option>
            <?php for ($mes = 1; $mes <= 12; $mes++): ?>
                <option value="<?= $mes ?>" <?= (string) ($valoresTarjeta['mes_vencimiento'] ?? '') === (string) $mes ? 'selected' : '' ?>><?= str_pad((string) $mes, 2, '0', STR_PAD_LEFT) ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="anio_vencimiento">Año de vencimiento</label>
        <select class="form-select" id="anio_vencimiento" name="anio_vencimiento" required data-regla="anio|vencimiento:mes_vencimiento:anio_vencimiento">
            <option value="">Año</option>
            <?php for ($anio = $anioActual; $anio <= $anioActual + 15; $anio++): ?>
                <option value="<?= $anio ?>" <?= (string) ($valoresTarjeta['anio_vencimiento'] ?? '') === (string) $anio ? 'selected' : '' ?>><?= $anio ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-4 campo">
        <label class="form-label" for="cvv">CVV</label>
        <input class="form-control" type="password" id="cvv" name="cvv" maxlength="4" inputmode="numeric" required data-regla="cvv" data-solo-digitos autocomplete="cc-csc">
    </div>
</div>
<p class="form-text mb-3">Por su seguridad, solo guardamos los últimos cuatro dígitos de la tarjeta; el CVV nunca se almacena.</p>
