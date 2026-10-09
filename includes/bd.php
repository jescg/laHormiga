<?php
/**
 * Conexión única a la base de datos mediante PDO.
 */
function conexion(): PDO
{
    static $conexion = null;

    if ($conexion === null) {
        $cadenaConexion = 'mysql:host=' . BD_HOST . ';dbname=' . BD_NOMBRE . ';charset=utf8mb4';
        try {
            $conexion = new PDO($cadenaConexion, BD_USUARIO, BD_CONTRASENA, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $excepcion) {
            error_log('Error de conexión: ' . $excepcion->getMessage());
            http_response_code(503);
            exit('El servicio no está disponible en este momento. Intente más tarde.');
        }
    }

    return $conexion;
}

/** Ejecuta una consulta preparada y devuelve el PDOStatement. */
function consulta(string $sql, array $parametros = []): PDOStatement
{
    $sentencia = conexion()->prepare($sql);
    $sentencia->execute($parametros);
    return $sentencia;
}

function obtenerUno(string $sql, array $parametros = []): ?array
{
    $fila = consulta($sql, $parametros)->fetch();
    return $fila === false ? null : $fila;
}

function obtenerTodos(string $sql, array $parametros = []): array
{
    return consulta($sql, $parametros)->fetchAll();
}

function obtenerValor(string $sql, array $parametros = [])
{
    $valor = consulta($sql, $parametros)->fetchColumn();
    return $valor === false ? null : $valor;
}
