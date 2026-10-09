<?php
/**
 * Lógica de datos del cliente: direcciones y métodos de pago.
 */

function leerDatosDireccion(): array
{
    return [
        'calle'           => entrada('calle'),
        'numero_exterior' => entrada('numero_exterior'),
        'numero_interior' => entrada('numero_interior'),
        'colonia'         => entrada('colonia'),
        'codigo_postal'   => entrada('codigo_postal'),
        'municipio'       => entrada('municipio'),
        'ciudad'          => entrada('ciudad'),
        'pais_id'         => entrada('pais_id', '1'),
    ];
}

function validarDatosDireccion(array $direccion): array
{
    $errores = [];
    if (!validarTextoRequerido($direccion['calle'], 120)) {
        $errores[] = 'Escriba la calle (máximo 120 caracteres).';
    }
    if (!preg_match('/^[A-Za-z0-9 \/-]{1,10}$/', $direccion['numero_exterior'])) {
        $errores[] = 'El número exterior es obligatorio (máximo 10 caracteres alfanuméricos).';
    }
    if ($direccion['numero_interior'] !== '' && !preg_match('/^[A-Za-z0-9 \/-]{1,10}$/', $direccion['numero_interior'])) {
        $errores[] = 'El número interior admite hasta 10 caracteres alfanuméricos.';
    }
    if (!validarTextoRequerido($direccion['colonia'], 100)) {
        $errores[] = 'Escriba la colonia.';
    }
    if (!validarCodigoPostal($direccion['codigo_postal'])) {
        $errores[] = 'El código postal debe tener 5 dígitos.';
    }
    if (!validarTextoRequerido($direccion['municipio'], 100)) {
        $errores[] = 'Escriba el municipio o alcaldía.';
    }
    if (!validarTextoRequerido($direccion['ciudad'], 100)) {
        $errores[] = 'Escriba la ciudad.';
    }
    if (!obtenerValor('SELECT 1 FROM paises WHERE id = ? AND activo = 1', [(int) $direccion['pais_id']])) {
        $errores[] = 'Seleccione un país válido.';
    }
    return $errores;
}

function guardarDireccion(int $usuarioId, array $direccion, bool $esPrincipal, ?int $direccionId = null): int
{
    $parametros = [
        $direccion['calle'], $direccion['numero_exterior'], $direccion['numero_interior'] !== '' ? $direccion['numero_interior'] : null,
        $direccion['colonia'], $direccion['codigo_postal'], $direccion['municipio'], $direccion['ciudad'], (int) $direccion['pais_id'],
    ];
    $tieneDirecciones = (bool) obtenerValor('SELECT 1 FROM direcciones WHERE usuario_id = ? AND activo = 1 LIMIT 1', [$usuarioId]);

    if ($direccionId === null) {
        consulta(
            'INSERT INTO direcciones (calle, numero_exterior, numero_interior, colonia, codigo_postal, municipio, ciudad, pais_id, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($parametros, [$usuarioId])
        );
        $direccionId = (int) conexion()->lastInsertId();
    } else {
        consulta(
            'UPDATE direcciones SET calle = ?, numero_exterior = ?, numero_interior = ?, colonia = ?, codigo_postal = ?, municipio = ?, ciudad = ?, pais_id = ?
              WHERE usuario_id = ? AND id = ?',
            array_merge($parametros, [$usuarioId, $direccionId])
        );
    }
    if ($esPrincipal || !$tieneDirecciones) {
        marcarDireccionPrincipal($usuarioId, $direccionId);
    }
    return $direccionId;
}

function marcarDireccionPrincipal(int $usuarioId, int $direccionId): void
{
    consulta('UPDATE direcciones SET es_principal = (id = ?) WHERE usuario_id = ?', [$direccionId, $usuarioId]);
}

function leerDatosTarjeta(): array
{
    return [
        'titular'          => mb_strtoupper(entrada('titular'), 'UTF-8'),
        'numero_tarjeta'   => preg_replace('/\D+/', '', entrada('numero_tarjeta')),
        'mes_vencimiento'  => entrada('mes_vencimiento'),
        'anio_vencimiento' => entrada('anio_vencimiento'),
        'cvv'              => entrada('cvv'),
    ];
}

function validarDatosTarjeta(array $tarjeta): array
{
    $errores = [];
    if (!validarNombrePersona($tarjeta['titular']) || mb_strlen($tarjeta['titular'], 'UTF-8') > 120) {
        $errores[] = 'Escriba el nombre del titular tal como aparece en la tarjeta.';
    }
    if (!validarLuhn($tarjeta['numero_tarjeta'])) {
        $errores[] = 'El número de tarjeta no es válido.';
    }
    if (!validarVencimiento((int) $tarjeta['mes_vencimiento'], (int) $tarjeta['anio_vencimiento'])) {
        $errores[] = 'La fecha de vencimiento no es válida o la tarjeta está vencida.';
    }
    if (!validarCvv($tarjeta['cvv'])) {
        $errores[] = 'El CVV debe tener 3 o 4 dígitos.';
    }
    return $errores;
}

/** Solo se guardan los últimos cuatro dígitos; el número completo y el CVV se descartan. */
function guardarTarjeta(int $usuarioId, array $tarjeta, bool $esPrincipal): int
{
    $tieneTarjetas = (bool) obtenerValor('SELECT 1 FROM metodos_pago WHERE usuario_id = ? AND activo = 1 LIMIT 1', [$usuarioId]);
    consulta(
        'INSERT INTO metodos_pago (usuario_id, tipo_tarjeta_id, titular, ultimos_digitos, mes_vencimiento, anio_vencimiento)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            $usuarioId,
            detectarTipoTarjeta($tarjeta['numero_tarjeta']),
            $tarjeta['titular'],
            substr($tarjeta['numero_tarjeta'], -4),
            (int) $tarjeta['mes_vencimiento'],
            (int) $tarjeta['anio_vencimiento'],
        ]
    );
    $tarjetaId = (int) conexion()->lastInsertId();
    if ($esPrincipal || !$tieneTarjetas) {
        marcarTarjetaPrincipal($usuarioId, $tarjetaId);
    }
    return $tarjetaId;
}

function marcarTarjetaPrincipal(int $usuarioId, int $tarjetaId): void
{
    consulta('UPDATE metodos_pago SET es_principal = (id = ?) WHERE usuario_id = ?', [$tarjetaId, $usuarioId]);
}

/** Si se elimina la principal, la más reciente pasa a ser principal. */
function reasignarPrincipal(string $tabla, int $usuarioId): void
{
    $tablasPermitidas = ['direcciones', 'metodos_pago'];
    if (!in_array($tabla, $tablasPermitidas, true)) {
        return;
    }
    $tienePrincipal = obtenerValor("SELECT 1 FROM $tabla WHERE usuario_id = ? AND activo = 1 AND es_principal = 1", [$usuarioId]);
    if (!$tienePrincipal) {
        $siguienteId = obtenerValor("SELECT id FROM $tabla WHERE usuario_id = ? AND activo = 1 ORDER BY id DESC LIMIT 1", [$usuarioId]);
        if ($siguienteId) {
            consulta("UPDATE $tabla SET es_principal = (id = ?) WHERE usuario_id = ?", [$siguienteId, $usuarioId]);
        }
    }
}

function direccionEnTexto(array $direccion): string
{
    $numero = $direccion['numero_exterior'] . ($direccion['numero_interior'] ? ', int. ' . $direccion['numero_interior'] : '');
    return $direccion['calle'] . ' ' . $numero . ', col. ' . $direccion['colonia'] . ', C.P. ' . $direccion['codigo_postal']
        . ', ' . $direccion['municipio'] . ', ' . $direccion['ciudad'] . ', ' . $direccion['pais'];
}

function direccionesDelUsuario(int $usuarioId): array
{
    return obtenerTodos(
        'SELECT d.*, p.nombre AS pais FROM direcciones d JOIN paises p ON p.id = d.pais_id
          WHERE d.usuario_id = ? AND d.activo = 1 ORDER BY d.es_principal DESC, d.id DESC',
        [$usuarioId]
    );
}

function tarjetasDelUsuario(int $usuarioId): array
{
    return obtenerTodos(
        'SELECT m.*, t.nombre AS tipo FROM metodos_pago m JOIN tipos_tarjeta t ON t.id = m.tipo_tarjeta_id
          WHERE m.usuario_id = ? AND m.activo = 1 ORDER BY m.es_principal DESC, m.id DESC',
        [$usuarioId]
    );
}

function tarjetaVencida(array $tarjeta): bool
{
    return !validarVencimiento((int) $tarjeta['mes_vencimiento'], (int) $tarjeta['anio_vencimiento']);
}
