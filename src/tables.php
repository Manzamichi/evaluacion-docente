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
 *                 opciones_de  Igual que opciones, pero salen de otra tabla:
 *                            ['tabla' => 'categorias', 'muestra' => 'nombre'].
 *                            La tabla debe estar declarada aquí (whitelist).
 *                            Con 'guarda' => 'id' se muestra 'muestra' pero se
 *                            guarda el id: es lo que necesita una FK a id.
 *                 hash       true para guardar con password_hash() en vez de texto plano
 *                 min, max   Rango de un entero (tipo number). Van en el <input>
 *                            y se vuelven a comprobar al guardar.
 *                 defecto    Valor con el que arranca el campo en un alta
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
                'etiqueta'    => 'Categoría',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'categorias', 'muestra' => 'nombre'],
            ],
            'orden' => [
                'etiqueta' => 'Orden',
                'tipo'     => 'number',
            ],
        ],
    ],

    'categorias' => [
        'etiqueta' => 'Categorías',
        'listar'   => ['id', 'nombre', 'orden'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'orden' => [
                'etiqueta' => 'Orden',
                'tipo'     => 'number',
            ],
        ],
    ],

    'instrumentos' => [
        'etiqueta' => 'Instrumentos',
        'listar'   => ['id', 'nombre', 'fecha_elaboracion'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'fecha_elaboracion' => [
                'etiqueta'  => 'Fecha de elaboración',
                'tipo'      => 'date',
                'requerido' => true,
            ],
            'instruccion' => [
                'etiqueta' => 'Instrucción',
                'tipo'     => 'textarea',
            ],
        ],
    ],

    'dimensiones' => [
        'etiqueta' => 'Dimensiones',
        'listar'   => ['id', 'nombre', 'comentarios'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'comentarios' => [
                'etiqueta' => 'Comentarios',
                'tipo'     => 'text',
            ],
        ],
    ],

    'tipo_preguntas' => [
        'etiqueta' => 'Tipos de pregunta',
        'listar'   => ['id', 'nombre', 'tipo'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'tipo' => [
                'etiqueta'  => 'Tipo',
                'tipo'      => 'number',
                'requerido' => true,
                'min'       => 1,
                'max'       => 255, // tope de TINYINT UNSIGNED
                'defecto'   => 1,
            ],
            // Opcional: un tipo "instrucciones" se muestra en el instrumento
            // pero no espera respuesta.
            'respuesta' => [
                'etiqueta' => 'Respuesta',
                'tipo'     => 'textarea',
            ],
        ],
    ],

    'preguntas' => [
        'etiqueta' => 'Preguntas',
        'listar'   => ['id', 'instrumento_id', 'dimension_id', 'tipo_id', 'orden', 'pregunta'],
        'campos'   => [
            'instrumento_id' => [
                'etiqueta'    => 'Instrumento',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'instrumentos', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'dimension_id' => [
                'etiqueta'    => 'Dimensión',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'dimensiones', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'tipo_id' => [
                'etiqueta'    => 'Tipo de pregunta',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'tipo_preguntas', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'pregunta' => [
                'etiqueta'  => 'Pregunta',
                'tipo'      => 'textarea',
                'requerido' => true,
            ],
            // Requerido: vacío llega como NULL y la columna es NOT NULL.
            'orden' => [
                'etiqueta'  => 'Orden',
                'tipo'      => 'number',
                'requerido' => true,
            ],
        ],
    ],
];
