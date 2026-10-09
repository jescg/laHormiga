<?php
require_once dirname(__DIR__) . '/includes/inicio.php';

$configuracionCatalogo = [
    'tabla'        => 'paises',
    'titulo'       => 'Países',
    'singular'     => 'país',
    'seccion'      => 'paises',
    'tiene_activo' => true,
    'orden'        => 'id',
    'protegidos'   => [1],
    'campos'       => [
        ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'texto', 'maximo' => 80, 'requerido' => true],
    ],
];
require dirname(__DIR__) . '/includes/catalogo_crud.php';
