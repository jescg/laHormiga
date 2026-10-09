<?php
/**
 * Configuración general de La Hormiga.
 *
 * Prioridad de valores:
 *   1. includes/config.local.php (solo en HostGator)
 *   2. Variables de entorno (Docker)
 *   3. Valores por omisión
 */

$configuracionLocal = [];
$rutaConfiguracionLocal = __DIR__ . '/config.local.php';
if (is_file($rutaConfiguracionLocal)) {
    $configuracionLocal = require $rutaConfiguracionLocal;
}

function valorConfiguracion(array $configuracionLocal, string $clave, string $valorPorOmision): string
{
    if (isset($configuracionLocal[$clave])) {
        return (string) $configuracionLocal[$clave];
    }
    $valorEntorno = getenv($clave);
    return $valorEntorno !== false ? $valorEntorno : $valorPorOmision;
}

define('BD_HOST',       valorConfiguracion($configuracionLocal, 'BD_HOST', 'localhost'));
define('BD_NOMBRE',     valorConfiguracion($configuracionLocal, 'BD_NOMBRE', 'la_hormiga'));
define('BD_USUARIO',    valorConfiguracion($configuracionLocal, 'BD_USUARIO', 'root'));
define('BD_CONTRASENA', valorConfiguracion($configuracionLocal, 'BD_CONTRASENA', ''));
define('URL_BASE',      rtrim(valorConfiguracion($configuracionLocal, 'URL_BASE', ''), '/'));
define('CORREO_TIENDA', valorConfiguracion($configuracionLocal, 'CORREO_TIENDA', 'no-responder@lahormiga.mx'));

define('NOMBRE_TIENDA', 'La Hormiga');
define('RAIZ_PUBLICA', dirname(__DIR__));
define('CARPETA_IMAGENES', 'uploads/productos');
define('TAMANO_MAXIMO_IMAGEN', 5 * 1024 * 1024);

// Versión de entrega: nunca mostrar errores en pantalla, solo registrarlos.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', RAIZ_PUBLICA . '/logs/php_errores.log');

date_default_timezone_set('America/Mexico_City');
