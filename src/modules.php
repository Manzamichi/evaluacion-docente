<?php

declare(strict_types=1);

/**
 * Registro de módulos: qué código ejecuta cada `url` de la tabla `modulos`.
 *
 * Este array es la whitelist. La base de datos dice qué módulos existen, cómo
 * se llaman en el menú y quién los ve; el código que se ejecuta sale de aquí y
 * solo de aquí. Una `url` que no esté en este array responde 404 aunque exista
 * en la base: nunca se arma la ruta de un include con datos de la base.
 *
 * Cada entrada usa una de estas dos formas:
 *
 *   'crud'    => 'usuarios'      El CRUD genérico sobre esa tabla de tables.php
 *   'archivo' => 'asignar.php'   Un archivo de src/modules/
 *
 * Las demás claves llegan al archivo como variable ($titulo, $pivote, ...).
 *
 * Para agregar un módulo:
 *   1. Agrégalo aquí
 *   2. Insértalo en la tabla `modulos` con esa misma url
 *   3. Asígnalo a los grupos que deban verlo (módulo "Grupos" > Permisos)
 */
return [
    'usuario/admin' => ['crud' => 'usuarios'],
    'grupo/admin'   => ['crud' => 'grupos'],
    'modulo/admin'  => ['crud' => 'modulos'],

    // Las dos pantallas de asignación N:N usan el mismo archivo con distinta
    // configuración: cambia el pivote y de qué tabla sale cada lista.
    'grupo/permisos' => [
        'archivo' => 'asignar.php',
        'titulo'  => 'Permisos del grupo',
        // Sin un ?id= de grupo no tiene sentido, así que no va en el menú: se
        // llega desde el listado de grupos.
        'oculto'  => true,
        'sujeto'  => ['tabla' => 'grupos', 'muestra' => 'nombre'],
        'pivote'  => ['tabla' => 'grupo_modulo', 'propia' => 'grupo_id', 'ajena' => 'modulo_id'],
        'destino' => [
            'tabla'    => 'modulos',
            'muestra'  => 'nombre',
            'agrupa'   => 'categoria',
            'etiqueta' => 'Módulos',
        ],
        'volver' => 'grupo/admin',
    ],

    // El mismo pivote visto desde el otro lado: qué grupos tiene un usuario.
    'usuario/grupos' => [
        'archivo' => 'asignar.php',
        'titulo'  => 'Grupos del usuario',
        'oculto'  => true,
        'sujeto'  => ['tabla' => 'usuarios', 'muestra' => 'nombre'],
        'pivote'  => ['tabla' => 'usuario_grupo', 'propia' => 'usuario_id', 'ajena' => 'grupo_id'],
        'destino' => [
            'tabla'    => 'grupos',
            'muestra'  => 'nombre',
            'etiqueta' => 'Grupos',
        ],
        'volver' => 'usuario/admin',
    ],

    'grupo/usuarios' => [
        'archivo' => 'asignar.php',
        'titulo'  => 'Usuarios del grupo',
        'oculto'  => true,
        'sujeto'  => ['tabla' => 'grupos', 'muestra' => 'nombre'],
        'pivote'  => ['tabla' => 'usuario_grupo', 'propia' => 'grupo_id', 'ajena' => 'usuario_id'],
        'destino' => [
            'tabla'    => 'usuarios',
            'muestra'  => 'nombre',
            'etiqueta' => 'Usuarios',
        ],
        'volver' => 'grupo/admin',
    ],
];
