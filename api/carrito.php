<?php
/**
 * API del carrito de compras (JSON).
 * Acciones: agregar, actualizar, eliminar.
 */
require_once dirname(__DIR__) . '/includes/inicio.php';
header('Content-Type: application/json; charset=UTF-8');

function responderJson(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function resumenCarrito(int $usuarioId): array
{
    $resumen = obtenerUno(
        'SELECT COALESCE(SUM(ci.cantidad), 0) AS articulos, COALESCE(SUM(ci.cantidad * p.precio), 0) AS subtotal
           FROM carrito_items ci JOIN productos p ON p.id = ci.producto_id
          WHERE ci.usuario_id = ?',
        [$usuarioId]
    );
    return ['articulos' => (int) $resumen['articulos'], 'subtotal' => (float) $resumen['subtotal']];
}

if (!esPost()) {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$usuario = usuarioActual();
if ($usuario === null) {
    $_SESSION['redirigir_despues'] = URL_BASE . '/tienda/producto.php?id=' . entradaEntero('producto_id');
    mensajeFlash('aviso', 'Inicie sesión para agregar productos a su carrito.');
    responderJson(['exito' => false, 'mensaje' => 'Inicie sesión para continuar.', 'redirigir' => url('login.php')], 401);
}
if ($usuario['rol'] !== ROL_CLIENTE) {
    responderJson(['exito' => false, 'mensaje' => 'Solo las cuentas de cliente pueden usar el carrito.'], 403);
}

$tokenRecibido = $_POST['token_csrf'] ?? '';
if (!is_string($tokenRecibido) || !hash_equals(tokenCsrf(), $tokenRecibido)) {
    responderJson(['exito' => false, 'mensaje' => 'La sesión expiró. Recargue la página.'], 400);
}

$usuarioId = (int) $usuario['id'];
$accion = entrada('accion');
$productoId = entradaEntero('producto_id');
$cantidadTexto = entrada('cantidad', '1');

$producto = obtenerUno('SELECT id, nombre, precio, stock FROM productos WHERE id = ? AND activo = 1', [$productoId]);
if ($producto === null && $accion !== 'eliminar') {
    responderJson(['exito' => false, 'mensaje' => 'El producto no está disponible.'], 404);
}

$mensajeSinExistencias = 'No se cuenta con la cantidad de unidades del producto «%s» para cumplir con el pedido.';

switch ($accion) {
    case 'agregar':
        if (!validarEnteroRango($cantidadTexto, 1, 99)) {
            responderJson(['exito' => false, 'mensaje' => 'La cantidad debe ser un número entre 1 y 99.'], 422);
        }
        $cantidadActual = (int) obtenerValor('SELECT cantidad FROM carrito_items WHERE usuario_id = ? AND producto_id = ?', [$usuarioId, $productoId]);
        $cantidadNueva = $cantidadActual + (int) $cantidadTexto;
        if ($cantidadNueva > 99) {
            responderJson(['exito' => false, 'mensaje' => 'Puede agregar como máximo 99 unidades por producto.'], 422);
        }
        if ($cantidadNueva > (int) $producto['stock']) {
            responderJson(['exito' => false, 'mensaje' => sprintf($mensajeSinExistencias, $producto['nombre']), 'resumen' => resumenCarrito($usuarioId)], 409);
        }
        consulta(
            'INSERT INTO carrito_items (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)',
            [$usuarioId, $productoId, $cantidadNueva]
        );
        responderJson(['exito' => true, 'mensaje' => '«' . $producto['nombre'] . '» se agregó a su carrito.', 'resumen' => resumenCarrito($usuarioId)]);
        break;

    case 'actualizar':
        $cantidadActual = (int) obtenerValor('SELECT cantidad FROM carrito_items WHERE usuario_id = ? AND producto_id = ?', [$usuarioId, $productoId]);
        if (!validarEnteroRango($cantidadTexto, 1, 99)) {
            responderJson(['exito' => false, 'mensaje' => 'La cantidad debe ser un número entre 1 y 99.', 'cantidad_actual' => $cantidadActual], 422);
        }
        if ((int) $cantidadTexto > (int) $producto['stock']) {
            responderJson(['exito' => false, 'mensaje' => sprintf($mensajeSinExistencias, $producto['nombre']), 'cantidad_actual' => $cantidadActual], 409);
        }
        consulta('UPDATE carrito_items SET cantidad = ? WHERE usuario_id = ? AND producto_id = ?', [(int) $cantidadTexto, $usuarioId, $productoId]);
        responderJson([
            'exito'   => true,
            'mensaje' => 'Cantidad actualizada.',
            'importe' => (float) $producto['precio'] * (int) $cantidadTexto,
            'resumen' => resumenCarrito($usuarioId),
        ]);
        break;

    case 'eliminar':
        consulta('DELETE FROM carrito_items WHERE usuario_id = ? AND producto_id = ?', [$usuarioId, $productoId]);
        responderJson(['exito' => true, 'mensaje' => 'El producto se eliminó de su carrito.', 'resumen' => resumenCarrito($usuarioId)]);
        break;

    default:
        responderJson(['exito' => false, 'mensaje' => 'Acción no válida.'], 400);
}
