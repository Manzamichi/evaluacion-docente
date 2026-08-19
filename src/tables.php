<?php

declare(strict_types=1);

/**
 * Configuración de las tablas del CRUD.
 *
 * Para dar de alta una tabla nueva:
 *   1. Créala en database/schema.sql
 *   2. Agrégala a este array
 * No hay que tocar ningún otro archivo.
 *
 * Estructura de cada tabla:
 *   etiqueta  Nombre visible en el menú y los títulos
 *   listar    Columnas que se muestran en la tabla del listado
 *   campos    Columnas editables desde el formulario
 *               etiqueta   Texto del <label>
 *               tipo       text | email | number | date | textarea | select | password
 *               requerido  true si no puede ir vacío
 *               opciones   Valores permitidos (solo para tipo select)
 *               hash       true para guardar con password_hash() en vez de texto plano
 *
 * Las columnas que la base de datos llena sola (id, TIMESTAMP con DEFAULT)
 * van en 'listar' pero no en 'campos'.
 */
return [
    'usuarios' => [
        'etiqueta' => 'Usuarios',
        'listar'   => ['id', 'nombre', 'correo', 'rol', 'creado_en'],
        'campos'   => [
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
            ],
            'rol' => [
                'etiqueta' => 'Rol',
                'tipo'     => 'select',
                'opciones' => ['admin', 'alumno', 'docente'],
            ],
        ],
    ],
];
