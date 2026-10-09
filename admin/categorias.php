<?php
require_once dirname(__DIR__) . '/includes/inicio.php';

$configuracionCatalogo = [
    'tabla'        => 'categorias',
    'titulo'       => 'Categorías',
    'singular'     => 'categoría',
    'seccion'      => 'categorias',
    'tiene_activo' => true,
    'orden'        => 'nombre',
    'campos'       => [
        ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'texto', 'maximo' => 80, 'requerido' => true],
        ['nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'tipo' => 'texto', 'maximo' => 255, 'requerido' => false],
    ],
];
require dirname(__DIR__) . '/includes/catalogo_crud.php';
