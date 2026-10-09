<?php
require_once dirname(__DIR__) . '/includes/inicio.php';

// Los estatus base (En proceso, Pagada, Enviado y Recibido) no pueden eliminarse.
$configuracionCatalogo = [
    'tabla'        => 'estatus_pedido',
    'titulo'       => 'Estatus de pedido',
    'singular'     => 'estatus',
    'seccion'      => 'estatus',
    'tiene_activo' => false,
    'orden'        => 'orden, id',
    'protegidos'   => [1, 2, 3, 4],
    'campos'       => [
        ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'texto', 'maximo' => 40, 'requerido' => true],
        ['nombre' => 'orden', 'etiqueta' => 'Orden', 'tipo' => 'entero', 'maximo' => 3, 'requerido' => true],
    ],
];
require dirname(__DIR__) . '/includes/catalogo_crud.php';
