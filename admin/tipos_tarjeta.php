<?php
require_once dirname(__DIR__) . '/includes/inicio.php';

// Los tipos 1 a 4 se usan para detectar la marca de la tarjeta automáticamente.
$configuracionCatalogo = [
    'tabla'        => 'tipos_tarjeta',
    'titulo'       => 'Tipos de tarjeta',
    'singular'     => 'tipo de tarjeta',
    'seccion'      => 'tipos_tarjeta',
    'tiene_activo' => true,
    'orden'        => 'id',
    'protegidos'   => [1, 2, 3, 4],
    'campos'       => [
        ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'texto', 'maximo' => 40, 'requerido' => true],
    ],
];
require dirname(__DIR__) . '/includes/catalogo_crud.php';
