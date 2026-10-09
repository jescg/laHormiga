<?php
/**
 * Funciones de apoyo: salida segura, rutas, mensajes, CSRF, validaciones e imágenes.
 */

// ---------------------------------------------------------------------
//  Salida y navegación
// ---------------------------------------------------------------------
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $ruta = '/'): string
{
    return URL_BASE . '/' . ltrim($ruta, '/');
}

function redirigir(string $ruta): void
{
    $destino = preg_match('#^https?://#', $ruta) ? $ruta : url($ruta);
    header('Location: ' . $destino);
    exit;
}

function esPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function entrada(string $clave, string $valorPorOmision = ''): string
{
    $valor = $_POST[$clave] ?? $_GET[$clave] ?? $valorPorOmision;
    if (!is_string($valor)) {
        return $valorPorOmision;
    }
    if (!mb_check_encoding($valor, 'UTF-8')) {
        $valor = mb_convert_encoding($valor, 'UTF-8', 'ISO-8859-1');
    }
    return trim($valor);
}

function entradaEntero(string $clave, int $valorPorOmision = 0): int
{
    $valor = entrada($clave);
    return preg_match('/^-?\d+$/', $valor) ? (int) $valor : $valorPorOmision;
}

function formatoMoneda($cantidad): string
{
    return '$' . number_format((float) $cantidad, 2, '.', ',');
}

function formatoFecha(?string $fecha, bool $conHora = false): string
{
    if (!$fecha) {
        return '';
    }
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $marca = strtotime($fecha);
    $texto = date('j', $marca) . ' ' . $meses[(int) date('n', $marca) - 1] . ' ' . date('Y', $marca);
    return $conHora ? $texto . ', ' . date('H:i', $marca) : $texto;
}

function nombreMes(int $numeroMes): string
{
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $meses[$numeroMes - 1] ?? '';
}

// ---------------------------------------------------------------------
//  Mensajes flash
// ---------------------------------------------------------------------
function mensajeFlash(string $tipo, string $texto): void
{
    $_SESSION['mensajes'][] = ['tipo' => $tipo, 'texto' => $texto];
}

function obtenerMensajes(): array
{
    $mensajes = $_SESSION['mensajes'] ?? [];
    unset($_SESSION['mensajes']);
    return $mensajes;
}

// ---------------------------------------------------------------------
//  Protección CSRF
// ---------------------------------------------------------------------
function tokenCsrf(): string
{
    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="token_csrf" value="' . e(tokenCsrf()) . '">';
}

function verificarCsrf(): void
{
    $tokenRecibido = $_POST['token_csrf'] ?? ($_SERVER['HTTP_X_TOKEN_CSRF'] ?? '');
    if (!is_string($tokenRecibido) || !hash_equals(tokenCsrf(), $tokenRecibido)) {
        http_response_code(400);
        mensajeFlash('error', 'La solicitud no es válida o la página expiró. Inténtelo de nuevo.');
        redirigir($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

// ---------------------------------------------------------------------
//  Validaciones del lado del servidor (replican validaciones.js)
// ---------------------------------------------------------------------
const PATRON_CONTRASENA = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,64}$/';
const PATRON_RFC        = '/^[A-ZÑ&]{4}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[A-Z0-9]{2}[0-9A]$/';
const PATRON_CURP       = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\d$/';
const PATRON_NOMBRE     = '/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\' .-]{2,80}$/u';

function validarEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 120;
}

function validarContrasena(string $contrasena): bool
{
    return (bool) preg_match(PATRON_CONTRASENA, $contrasena);
}

function validarRfc(string $rfc): bool
{
    return (bool) preg_match(PATRON_RFC, $rfc);
}

function validarCurp(string $curp): bool
{
    return (bool) preg_match(PATRON_CURP, $curp);
}

function validarNombrePersona(string $nombre): bool
{
    return (bool) preg_match(PATRON_NOMBRE, $nombre);
}

function validarTelefono(string $telefono): bool
{
    return (bool) preg_match('/^\d{10}$/', $telefono);
}

function validarCodigoPostal(string $codigoPostal): bool
{
    return (bool) preg_match('/^\d{5}$/', $codigoPostal);
}

function validarLuhn(string $numeroTarjeta): bool
{
    if (!preg_match('/^\d{13,19}$/', $numeroTarjeta)) {
        return false;
    }
    $suma = 0;
    $duplicar = false;
    for ($posicion = strlen($numeroTarjeta) - 1; $posicion >= 0; $posicion--) {
        $digito = (int) $numeroTarjeta[$posicion];
        if ($duplicar) {
            $digito *= 2;
            if ($digito > 9) {
                $digito -= 9;
            }
        }
        $suma += $digito;
        $duplicar = !$duplicar;
    }
    return $suma % 10 === 0;
}

function validarVencimiento(int $mes, int $anio): bool
{
    if ($mes < 1 || $mes > 12 || $anio < (int) date('Y') || $anio > (int) date('Y') + 20) {
        return false;
    }
    return ($anio * 100 + $mes) >= ((int) date('Y') * 100 + (int) date('n'));
}

function validarCvv(string $cvv): bool
{
    return (bool) preg_match('/^\d{3,4}$/', $cvv);
}

function validarPrecio(string $precio): bool
{
    return (bool) preg_match('/^\d{1,8}(\.\d{1,2})?$/', $precio) && (float) $precio > 0;
}

function validarEnteroRango(string $valor, int $minimo, int $maximo): bool
{
    return (bool) preg_match('/^\d+$/', $valor) && (int) $valor >= $minimo && (int) $valor <= $maximo;
}

function validarTextoRequerido(string $texto, int $longitudMaxima = 255): bool
{
    $longitud = mb_strlen($texto, 'UTF-8');
    return $longitud > 0 && $longitud <= $longitudMaxima;
}

function detectarTipoTarjeta(string $numeroTarjeta): int
{
    if (preg_match('/^4/', $numeroTarjeta)) {
        return 1; // Visa
    }
    if (preg_match('/^(5[1-5]|2[2-7])/', $numeroTarjeta)) {
        return 2; // Mastercard
    }
    if (preg_match('/^3[47]/', $numeroTarjeta)) {
        return 3; // American Express
    }
    return 4;
}

// ---------------------------------------------------------------------
//  Productos e imágenes
// ---------------------------------------------------------------------
function rutaImagen(?string $ruta): string
{
    return $ruta ? url($ruta) : url('assets/img/sin-imagen.svg');
}

/**
 * Guarda las imágenes recibidas en $_FILES[$campo] (input múltiple).
 * Devuelve la cantidad guardada y llena $errores con los archivos rechazados.
 */
function guardarImagenesProducto(int $productoId, string $campo, array &$errores): int
{
    if (empty($_FILES[$campo]) || !is_array($_FILES[$campo]['name'])) {
        return 0;
    }
    $tiposPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $archivos = $_FILES[$campo];
    $carpetaDestino = RAIZ_PUBLICA . '/' . CARPETA_IMAGENES;
    if (!is_dir($carpetaDestino)) {
        mkdir($carpetaDestino, 0755, true);
    }
    $ordenSiguiente = (int) obtenerValor('SELECT COALESCE(MAX(orden), -1) + 1 FROM producto_imagenes WHERE producto_id = ?', [$productoId]);
    $guardadas = 0;

    foreach ($archivos['name'] as $indice => $nombreOriginal) {
        if ($archivos['error'][$indice] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($archivos['error'][$indice] !== UPLOAD_ERR_OK) {
            $errores[] = 'No se pudo subir «' . $nombreOriginal . '».';
            continue;
        }
        if ($archivos['size'][$indice] > TAMANO_MAXIMO_IMAGEN) {
            $errores[] = '«' . $nombreOriginal . '» excede el tamaño máximo de 5 MB.';
            continue;
        }
        $tipoDetectado = (new finfo(FILEINFO_MIME_TYPE))->file($archivos['tmp_name'][$indice]);
        if (!isset($tiposPermitidos[$tipoDetectado]) || @getimagesize($archivos['tmp_name'][$indice]) === false) {
            $errores[] = '«' . $nombreOriginal . '» no es una imagen válida (JPG, PNG, WEBP o GIF).';
            continue;
        }
        $nombreArchivo = 'producto-' . $productoId . '-' . bin2hex(random_bytes(8)) . '.' . $tiposPermitidos[$tipoDetectado];
        if (move_uploaded_file($archivos['tmp_name'][$indice], $carpetaDestino . '/' . $nombreArchivo)) {
            consulta('INSERT INTO producto_imagenes (producto_id, ruta, orden) VALUES (?, ?, ?)', [
                $productoId, CARPETA_IMAGENES . '/' . $nombreArchivo, $ordenSiguiente++,
            ]);
            $guardadas++;
        } else {
            $errores[] = 'No se pudo guardar «' . $nombreOriginal . '».';
        }
    }
    return $guardadas;
}

function eliminarImagenProducto(int $imagenId): void
{
    $imagen = obtenerUno('SELECT ruta FROM producto_imagenes WHERE id = ?', [$imagenId]);
    if ($imagen === null) {
        return;
    }
    consulta('DELETE FROM producto_imagenes WHERE id = ?', [$imagenId]);
    // Solo se borran del disco las imágenes subidas (no las de demostración)
    if (strpos($imagen['ruta'], CARPETA_IMAGENES . '/') === 0) {
        $rutaFisica = RAIZ_PUBLICA . '/' . $imagen['ruta'];
        if (is_file($rutaFisica)) {
            unlink($rutaFisica);
        }
    }
}

function estrellasHtml(float $promedio, ?int $totalCalificaciones = null): string
{
    $redondeado = (int) round($promedio);
    $html = '<span class="estrellas" aria-label="' . e(number_format($promedio, 1)) . ' de 5 estrellas">';
    for ($posicion = 1; $posicion <= 5; $posicion++) {
        $html .= '<span class="estrella' . ($posicion <= $redondeado ? ' llena' : '') . '">&#9733;</span>';
    }
    $html .= '</span>';
    if ($totalCalificaciones !== null) {
        $html .= ' <small class="text-muted">(' . $totalCalificaciones . ')</small>';
    }
    return $html;
}

function categoriasActivas(): array
{
    static $categorias = null;
    if ($categorias === null) {
        $categorias = obtenerTodos('SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre');
    }
    return $categorias;
}

function totalArticulosCarrito(): int
{
    $usuario = usuarioActual();
    if ($usuario === null || $usuario['rol'] !== ROL_CLIENTE) {
        return 0;
    }
    return (int) obtenerValor('SELECT COALESCE(SUM(cantidad), 0) FROM carrito_items WHERE usuario_id = ?', [$usuario['id']]);
}

function clienteComproProducto(int $usuarioId, int $productoId): bool
{
    return (bool) obtenerValor(
        'SELECT 1 FROM pedido_detalle d JOIN pedidos p ON p.id = d.pedido_id
          WHERE p.usuario_id = ? AND d.producto_id = ? LIMIT 1',
        [$usuarioId, $productoId]
    );
}

/** Envía un correo de notificación; si el servidor no tiene correo, falla en silencio. */
function enviarCorreo(string $destinatario, string $asunto, string $mensaje): void
{
    $encabezados = 'From: ' . NOMBRE_TIENDA . ' <' . CORREO_TIENDA . ">\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    if (!@mail($destinatario, $asuntoCodificado, $mensaje, $encabezados)) {
        error_log('No se pudo enviar el correo a ' . $destinatario);
    }
}

// ---------------------------------------------------------------------
//  Consultas y fragmentos reutilizables de la tienda
// ---------------------------------------------------------------------
const SQL_PRODUCTOS_TIENDA = 'SELECT p.id, p.sku, p.nombre, p.precio, p.dias_entrega, p.categoria_id, c.nombre AS categoria,
        (SELECT i.ruta FROM producto_imagenes i WHERE i.producto_id = p.id ORDER BY i.orden, i.id LIMIT 1) AS imagen,
        (SELECT AVG(ca.estrellas) FROM calificaciones ca WHERE ca.producto_id = p.id) AS promedio,
        (SELECT COUNT(*) FROM calificaciones ca WHERE ca.producto_id = p.id) AS total_calificaciones
   FROM productos p
   JOIN categorias c ON c.id = p.categoria_id
  WHERE p.activo = 1 AND c.activo = 1';

function precioHtml($precio, string $claseExtra = 'fs-5'): string
{
    return '<span class="fw-bold ' . e($claseExtra) . '">' . e(formatoMoneda($precio)) . '</span>';
}

function textoEntrega(int $diasEntrega): string
{
    $fechaEntrega = date('Y-m-d', strtotime('+' . $diasEntrega . ' days'));
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $diaSemana = $dias[(int) date('w', strtotime($fechaEntrega))];
    return 'Llega el ' . $diaSemana . ', ' . formatoFecha($fechaEntrega)
        . ' (' . $diasEntrega . ($diasEntrega === 1 ? ' día' : ' días') . ')';
}

function tarjetaProductoHtml(array $producto): string
{
    $urlProducto = e(url('tienda/producto.php?id=' . (int) $producto['id']));
    $html = '<div class="col"><div class="card h-100 card-producto">'
        . '<a href="' . $urlProducto . '"><img class="card-img-top imagen-producto" src="' . e(rutaImagen($producto['imagen'])) . '" alt="' . e($producto['nombre']) . '" loading="lazy"></a>'
        . '<div class="card-body d-flex flex-column">'
        . '<small class="text-muted">' . e($producto['categoria']) . '</small>'
        . '<h6 class="card-title"><a class="link-dark link-underline-opacity-0" href="' . $urlProducto . '">' . e($producto['nombre']) . '</a></h6>'
        . '<div class="small">' . estrellasHtml((float) $producto['promedio'], (int) $producto['total_calificaciones']) . '</div>'
        . '<div class="my-1">' . precioHtml($producto['precio']) . '</div>'
        . '<small class="text-muted mb-3">' . e(textoEntrega((int) $producto['dias_entrega'])) . '</small>';

    $usuario = usuarioActual();
    if ($usuario === null || $usuario['rol'] === ROL_CLIENTE) {
        $html .= '<form class="mt-auto" method="post" action="' . e(url('api/carrito.php')) . '" data-agregar-carrito>'
            . '<input type="hidden" name="producto_id" value="' . (int) $producto['id'] . '">'
            . '<input type="hidden" name="cantidad" value="1">'
            . '<button type="submit" class="btn btn-hormiga btn-sm w-100"><i class="bi bi-cart-plus"></i> Agregar al carrito</button>'
            . '</form>';
    }
    return $html . '</div></div></div>';
}

/**
 * Procesa las acciones de la galería de un producto: subir, eliminar y marcar como principal.
 * Devuelve true si se procesó alguna acción.
 */
function procesarAccionImagenes(int $productoId): bool
{
    $accion = entrada('accion');
    if ($accion === 'subir_imagenes') {
        $errores = [];
        $guardadas = guardarImagenesProducto($productoId, 'imagenes', $errores);
        foreach ($errores as $error) {
            mensajeFlash('error', $error);
        }
        if ($guardadas > 0) {
            mensajeFlash('exito', $guardadas === 1 ? 'Se agregó 1 imagen.' : 'Se agregaron ' . $guardadas . ' imágenes.');
        } elseif (!$errores) {
            mensajeFlash('aviso', 'Seleccione al menos una imagen.');
        }
        return true;
    }
    $imagenId = entradaEntero('imagen_id');
    $perteneceAlProducto = obtenerValor('SELECT 1 FROM producto_imagenes WHERE id = ? AND producto_id = ?', [$imagenId, $productoId]);
    if ($accion === 'eliminar_imagen' && $perteneceAlProducto) {
        eliminarImagenProducto($imagenId);
        mensajeFlash('exito', 'La imagen se eliminó.');
        return true;
    }
    if ($accion === 'imagen_principal' && $perteneceAlProducto) {
        consulta('UPDATE producto_imagenes SET orden = orden + 1 WHERE producto_id = ?', [$productoId]);
        consulta('UPDATE producto_imagenes SET orden = 0 WHERE id = ?', [$imagenId]);
        mensajeFlash('exito', 'Se cambió la imagen principal.');
        return true;
    }
    return false;
}

/** Clase de Bootstrap para la etiqueta de cada estatus de pedido. */
function claseEstatus(int $estatusId): string
{
    $clases = [1 => 'text-bg-warning', 2 => 'text-bg-primary', 3 => 'text-bg-info', 4 => 'text-bg-success'];
    return $clases[$estatusId] ?? 'text-bg-secondary';
}
