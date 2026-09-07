<?php

declare(strict_types=1);

/**
 * Configuración de las tablas que atiende el CRUD genérico.
 *
 * Para dar de alta una tabla nueva:
 *   1. Créala en database/schema.sql
 *   2. Agrégala a este array
 *   3. Registra su módulo en src/modules.php y en la tabla `modulos`
 *
 * Estructura de cada tabla:
 *   etiqueta    Nombre visible en los títulos
 *   por_pagina  Filas por página en el listado (opcional; por defecto FILAS_POR_PAGINA = 10)
 *   listar      Columnas que se muestran en la tabla del listado
 *   campos      Columnas editables desde el formulario
 *                 etiqueta   Texto del <label>
 *                 tipo       text | email | number | date | textarea | select | password
 *                 requerido  true si no puede ir vacío
 *                 opciones   Valores permitidos (solo para tipo select)
 *                 hash       true para guardar con password_hash() en vez de texto plano
 *   acciones    Enlaces extra por fila hacia otro módulo, que reciben ?id=
 *   ocultar     Columnas que el panel de detalle nunca muestra, porque son
 *               sensibles (tokens, CURP, RFC). Las que ya tienen 'hash' => true
 *               se excluyen solas. Una columna sensible se declara aquí, o se
 *               muestra: el panel enseña todo lo que la tabla tiene en la base.
 *
 * Las columnas que la base de datos llena sola (id, TIMESTAMP con DEFAULT) y
 * las que no deben tocarse desde la interfaz van en 'listar' pero no en 'campos'.
 *
 * Toda tabla que se declare aquí necesita la columna `editado_por VARCHAR(32)`:
 * crear() y actualizar() la escriben siempre, y sin ella el INSERT falla.
 */
return [
    'usuarios' => [
        'etiqueta'   => 'Usuarios',
        'por_pagina' => 10,
        'listar'     => ['id', 'usuario', 'nombre', 'correo', 'creado_en'],
        'campos'     => [
            'usuario' => [
                'etiqueta'  => 'Usuario',
                'tipo'      => 'text',
                'requerido' => true,
                'patron'    => 'validarUsuarioGenerico',
            ],
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'correo' => [
                'etiqueta'  => 'Correo',
                'tipo'      => 'email',
                'requerido' => true,
            ],
            'password_hash' => [
                'etiqueta'  => 'Contraseña',
                'tipo'      => 'password',
                'requerido' => true,
                'hash'      => true,
                'patron'    => 'validarPasswordGenerica',
            ],
        ],
        'acciones' => [
            ['etiqueta' => 'Grupos', 'modulo' => 'usuario/grupos'],
        ],
    ],

    'grupos' => [
        'etiqueta' => 'Grupos',
        // es_admin se muestra pero no se edita: darse de alta como admin desde
        // la interfaz sería una escalada de privilegios. Se marca en la base.
        'listar'   => ['id', 'nombre', 'descripcion', 'es_admin'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'descripcion' => [
                'etiqueta' => 'Descripción',
                'tipo'     => 'text',
            ],
        ],
        'acciones' => [
            ['etiqueta' => 'Permisos', 'modulo' => 'grupo/permisos'],
            ['etiqueta' => 'Usuarios', 'modulo' => 'grupo/usuarios'],
        ],
    ],

    'modulos' => [
        'etiqueta' => 'Módulos',
        'listar'   => ['id', 'nombre', 'descripcion', 'url', 'categoria', 'orden'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'descripcion' => [
                'etiqueta' => 'Descripción',
                'tipo'     => 'text',
            ],
            'url' => [
                'etiqueta'  => 'Url',
                'tipo'      => 'text',
                'requerido' => true,
                'patron'    => 'validarUrlModulo',
            ],
            'categoria' => [
                'etiqueta'  => 'Categoría',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'orden' => [
                'etiqueta' => 'Orden',
                'tipo'     => 'number',
            ],
        ],
    ],
];
