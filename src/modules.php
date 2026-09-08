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
 * Con 'acciones' se cuelgan del módulo, en el menú lateral, las acciones que no
 * dependen de ningún registro. Cada una apunta a uno de dos lados:
 *
 *   ['etiqueta' => 'Exportar CSV', 'params' => ['accion' => 'exportar']]
 *   ['etiqueta' => 'Reporte',      'modulo' => 'reporte/general']
 *
 * Con 'params' el enlace va al mismo módulo con esos parámetros; con 'modulo' va
 * a otro, y solo se pinta si el usuario tiene permiso sobre él. No confundirla
 * con la clave 'acciones' de tables.php, que son enlaces por *registro* y se
 * pintan en el panel de detalle.
 *
 * Para agregar un módulo:
 *   1. Agrégalo aquí
 *   2. Insértalo en la tabla `modulos` con esa misma url
 *   3. Asígnalo a los grupos que deban verlo (módulo "Grupos" > Permisos)
 */
return [
    'usuario/admin' => [
        'crud' => 'usuarios',
        // Sin 'Importar CSV': la contraseña es obligatoria en cada alta nueva,
        // así que una carga masiva pediría una columna de contraseñas en claro
        // dentro del archivo. Eso se resuelve aparte, no con esta acción.
        'acciones' => [
            ['etiqueta' => 'Exportar CSV', 'params' => ['accion' => 'exportar']],
            // Sin ?id=: la pantalla pregunta de qué usuario antes de abrir el
            // dual box. El atajo con el usuario ya elegido está en el panel de
            // detalle, que es de dónde se llega con una fila en la mano.
            ['etiqueta' => 'Grupos de un usuario', 'modulo' => 'usuario/grupos'],
        ],
    ],

    'grupo/admin' => [
        'crud'     => 'grupos',
        'acciones' => [
            ['etiqueta' => 'Exportar CSV', 'params' => ['accion' => 'exportar']],
            ['etiqueta' => 'Importar CSV', 'params' => ['accion' => 'importar']],
            ['etiqueta' => 'Permisos de un grupo', 'modulo' => 'grupo/permisos'],
            ['etiqueta' => 'Usuarios de un grupo', 'modulo' => 'grupo/usuarios'],
        ],
    ],

    'modulo/admin' => [
        'crud'     => 'modulos',
        'acciones' => [
            ['etiqueta' => 'Exportar CSV', 'params' => ['accion' => 'exportar']],
            ['etiqueta' => 'Importar CSV', 'params' => ['accion' => 'importar']],
        ],
    ],

    // Las dos pantallas de asignación N:N usan el mismo archivo con distinta
    // configuración: cambia el pivote y de qué tabla sale cada lista.
    'grupo/permisos' => [
        'archivo' => 'asignar.php',
        'titulo'  => 'Permisos del grupo',
        // No va en la lista de módulos del menú: se llega desde el panel de
        // detalle de un grupo, o desde las acciones de 'grupo/admin', que
        // entran sin ?id= y preguntan el grupo primero.
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
