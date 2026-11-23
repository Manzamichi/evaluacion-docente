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
 *                 tipo       text | email | number | date | textarea | select | password | pares | html
 *                            html: editor con formato (Quill). Se guarda HTML pasado
 *                            por limpiaHtml() (helpers/html.php); la columna, TEXT.
 *                            pares: lista de opciones valor:respuesta con botones + y −,
 *                            guardada como "1:Sí,2:No". El valor es entero y la
 *                            respuesta no lleva comas.
 *                 requerido  true si no puede ir vacío
 *                 opciones   Valores permitidos (solo para tipo select)
 *                 etiquetas  Texto que muestra cada opción: ['1' => 'Abierta'] (opcional)
 *                 solo_si    ['otra_columna', 'valor']: el campo solo aplica cuando
 *                            esa columna vale eso. Si no, se oculta y se guarda NULL.
 *                 opciones_de  Igual que opciones, pero salen de otra tabla:
 *                            ['tabla' => 'categorias', 'muestra' => 'nombre'].
 *                            La tabla debe estar declarada aquí (whitelist).
 *                            Con 'guarda' => 'id' se muestra 'muestra' pero se
 *                            guarda el id: es lo que necesita una FK a id.
 *                 hash       true para guardar con password_hash() en vez de texto plano
 *                 min, max   Rango de un entero (tipo number). Van en el <input>
 *                            y se vuelven a comprobar al guardar.
 *                 defecto    Valor con el que arranca el campo en un alta
 *                 al_final_de  Columna de grupo (p. ej. 'instrumento_id'). En un alta
 *                            el campo no se muestra y crear() pone MAX + 1 de ese
 *                            grupo: el registro nuevo va al final.
 *   acciones    Enlaces extra por fila hacia otro módulo, que reciben ?id=
 *   ocultar     Columnas que el panel de detalle nunca muestra, porque son
 *               sensibles (tokens, CURP, RFC). Las que ya tienen 'hash' => true
 *               se excluyen solas. Una columna sensible se declara aquí, o se
 *               muestra: el panel enseña todo lo que la tabla tiene en la base.
 *   usuario_propio  El registro es dueño de un usuario (profesor, alumno): se
 *               crea, edita y elimina con él, en la misma transacción.
 *                 columna  FK a usuarios.id (p. ej. 'usuario_id')
 *                 correo   Columna que se copia a usuarios.correo
 *                 grupo    Grupo (grupos.nombre) que recibe el usuario al alta
 *                 tipo     usuarios.tipo (1 = profesor, 2 = alumno)
 *               Los campos con 'de_usuario' => true se piden en el formulario
 *               pero se guardan en `usuarios`, no en esta tabla. Al importar,
 *               el usuario sale del correo (lo que va antes de la @) y la
 *               contraseña de la variable de entorno PASSWORD_GENERICA.
 *   importar    Cómo leer el archivo que se importa (opcional):
 *                 llave        Columna única: una fila cuya llave ya existe
 *                              actualiza ese registro en vez de crear otro
 *                 encabezados  Alias del archivo => columna, para archivos que
 *                              vienen de otro sistema ('ClaveProfesor' => 'clave')
 *
 * Las columnas que la base de datos llena sola (id, TIMESTAMP con DEFAULT) y
 * las que no deben tocarse desde la interfaz van en 'listar' pero no en 'campos'.
 *
 * Toda tabla que se declare aquí necesita las columnas de auditoría
 * (creado_en, creado_por, editado_en, editado_por; ver database/schema.sql):
 * crear() escribe creado_por y editado_por, actualizar() editado_por, y sin
 * ellas el INSERT falla.
 */
return [
    'usuarios' => [
        'etiqueta'   => 'Usuarios',
        'por_pagina' => 10,
        'listar'     => ['id', 'usuario', 'correo', 'estado'],
        'campos'     => [
            'usuario' => [
                'etiqueta'  => 'Usuario',
                'tipo'      => 'text',
                'requerido' => true,
                'patron'    => 'validarUsuarioGenerico',
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
            // Un usuario inactivo no puede iniciar sesión (local_auth.php).
            'estado' => [
                'etiqueta'  => 'Estado',
                'tipo'      => 'select',
                'requerido' => true,
                'opciones'  => ['1', '0'],
                'etiquetas' => ['1' => 'Activo', '0' => 'Inactivo'],
                'defecto'   => '1',
            ],
        ],
        'acciones' => [
            ['etiqueta' => 'Grupos', 'modulo' => 'usuario/grupos'],
        ],
    ],

    'profesores' => [
        'etiqueta'       => 'Profesores',
        'listar'         => ['id', 'clave', 'nombres', 'apellido_paterno', 'apellido_materno', 'grado'],
        // usuario_id no se edita: el usuario nace y muere con el profesor.
        'usuario_propio' => [
            'columna' => 'usuario_id',
            'correo'  => 'correo',
            'grupo'   => 'Profesor',
            'tipo'    => 1,
        ],
        // ProfesoresConAlumnosInscritos.txt, el archivo del sistema escolar.
        'importar' => [
            'llave'       => 'clave',
            'encabezados' => [
                'ClaveProfesor'   => 'clave',
                'NombreProfesor'  => 'nombres',
                'ApellidoPaterno' => 'apellido_paterno',
                'ApellidoMaterno' => 'apellido_materno',
                'GradoAcademico'  => 'grado',
                'Correo'          => 'correo',
            ],
        ],
        'campos'         => [
            'clave' => [
                'etiqueta'  => 'Clave',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'nombres' => [
                'etiqueta'  => 'Nombres',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'apellido_paterno' => [
                'etiqueta'  => 'Apellido Paterno',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'apellido_materno' => [
                'etiqueta'  => 'Apellido Materno',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'grado' => [
                'etiqueta' => 'Grado',
                'tipo'     => 'text',
            ],
            'correo' => [
                'etiqueta'  => 'Correo',
                'tipo'      => 'email',
                'requerido' => true,
            ],
            'usuario' => [
                'etiqueta'   => 'Usuario',
                'tipo'       => 'text',
                'requerido'  => true,
                'patron'     => 'validarUsuarioGenerico',
                'de_usuario' => true,
            ],
            'password_hash' => [
                'etiqueta'   => 'Contraseña',
                'tipo'       => 'password',
                'requerido'  => true,
                'hash'       => true,
                'patron'     => 'validarPasswordGenerica',
                'de_usuario' => true,
            ],
            'estado' => [
                'etiqueta'   => 'Estado',
                'tipo'       => 'select',
                'requerido'  => true,
                'opciones'   => ['1', '0'],
                'etiquetas'  => ['1' => 'Activo', '0' => 'Inactivo'],
                'defecto'    => '1',
                'de_usuario' => true,
            ],
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
                'tipo'     => 'html',
            ],
        ],
        'acciones' => [
            ['etiqueta' => 'Preguntas', 'modulo' => 'instrumento/preguntas'],
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
        'listar'   => ['id', 'nombre', 'tipo', 'respuesta'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'tipo' => [
                'etiqueta'  => 'Tipo',
                'tipo'      => 'select',
                'requerido' => true,
                'opciones'  => ['1', '2', '3'],
                'etiquetas' => ['1' => 'Instrucción', '2' => 'Abierta', '3' => 'Opción múltiple'],
            ],
            // Solo la opción múltiple tiene respuestas; instrucción y abierta
            // se guardan con NULL.
            'respuesta' => [
                'etiqueta'  => 'Respuestas',
                'tipo'      => 'pares',
                'requerido' => true,
                'solo_si'   => ['tipo', '3'],
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
                'tipo'      => 'html',
                'requerido' => true,
            ],
            // En un alta no se pide: la pregunta va al final de su instrumento.
            // Al editar, mínimo 1. Lo normal es reordenar arrastrando, en
            // Instrumentos > Preguntas, que recorre solas a las demás.
            'orden' => [
                'etiqueta'    => 'Orden',
                'tipo'        => 'number',
                'requerido'   => true,
                'min'         => 1,
                'al_final_de' => 'instrumento_id',
            ],
        ],
    ],

    // --- Estructura académica ---

    'planes_estudio' => [
        'etiqueta' => 'Planes de estudio',
        'listar'   => ['id', 'nombre', 'siglas', 'clave', 'version', 'fecha_inicio'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'siglas' => [
                'etiqueta' => 'Siglas',
                'tipo'     => 'text',
            ],
            'clave' => [
                'etiqueta' => 'Clave',
                'tipo'     => 'text',
            ],
            'version' => [
                'etiqueta' => 'Versión',
                'tipo'     => 'text',
            ],
            'fecha_inicio' => [
                'etiqueta' => 'Fecha de inicio',
                'tipo'     => 'date',
            ],
            'comentarios' => [
                'etiqueta' => 'Comentarios',
                'tipo'     => 'text',
            ],
        ],
    ],

    'ciclos_escolares' => [
        'etiqueta' => 'Ciclos escolares',
        'listar'   => ['id', 'nombre', 'activo', 'orden'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            // ponytail: que solo haya un ciclo activo aún no se cuida; va en
            // la fase de funciones propias de cada pantalla.
            'activo' => [
                'etiqueta'  => 'Activo',
                'tipo'      => 'select',
                'requerido' => true,
                'opciones'  => ['1', '0'],
                'etiquetas' => ['1' => 'Sí', '0' => 'No'],
                'defecto'   => '0',
            ],
            'orden' => [
                'etiqueta' => 'Orden',
                'tipo'     => 'number',
                'min'      => 0,
            ],
            'comentarios' => [
                'etiqueta' => 'Comentarios',
                'tipo'     => 'text',
            ],
        ],
    ],

    'asignaturas' => [
        'etiqueta' => 'Asignaturas',
        'listar'   => ['id', 'clave', 'nombre', 'plan_id'],
        'campos'   => [
            'clave' => [
                'etiqueta'  => 'Clave',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'nombre' => [
                'etiqueta'  => 'Nombre',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'plan_id' => [
                'etiqueta'    => 'Plan de estudio',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'planes_estudio', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'comentarios' => [
                'etiqueta' => 'Comentarios',
                'tipo'     => 'text',
            ],
        ],
    ],

    // Los planes que coordina cada uno (pivote coordinador_plan) no son un
    // CRUD: se asignarán con una pantalla tipo asignar.php.
    'coordinadores' => [
        'etiqueta' => 'Coordinadores',
        'listar'   => ['id', 'nombre', 'profesor_id'],
        'campos'   => [
            'nombre' => [
                'etiqueta'  => 'Cargo',
                'tipo'      => 'text',
                'requerido' => true,
            ],
            'profesor_id' => [
                'etiqueta'    => 'Profesor (clave)',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'profesores', 'muestra' => 'clave', 'guarda' => 'id'],
            ],
        ],
    ],

    'cargas_academicas' => [
        'etiqueta' => 'Cargas académicas',
        'listar'   => ['id', 'ciclo_escolar_id', 'asignatura_id', 'profesor_id', 'matricula', 'clave_grupo', 'grupo'],
        'campos'   => [
            'ciclo_escolar_id' => [
                'etiqueta'    => 'Ciclo escolar',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'ciclos_escolares', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'asignatura_id' => [
                'etiqueta'    => 'Asignatura',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'asignaturas', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'profesor_id' => [
                'etiqueta'    => 'Profesor (clave)',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'profesores', 'muestra' => 'clave', 'guarda' => 'id'],
            ],
            // La matrícula del alumno, no una FK: alumnos no es un CRUD de
            // tables.php y la carga puede llegar antes que la cuenta.
            'matricula' => [
                'etiqueta'  => 'Matrícula',
                'tipo'      => 'text',
                'requerido' => true,
                'patron'    => 'validarMatriculaAlumno',
            ],
            'clave_grupo' => [
                'etiqueta' => 'Clave de grupo',
                'tipo'     => 'text',
            ],
            'grupo' => [
                'etiqueta' => 'Grupo',
                'tipo'     => 'text',
            ],
        ],
    ],

    // --- Evaluación ---

    'evaluaciones' => [
        'etiqueta' => 'Evaluaciones',
        'listar'   => ['id', 'fecha_inicio', 'fecha_fin', 'plan_id', 'ciclo_escolar_id', 'instrumento_id'],
        'campos'   => [
            'fecha_inicio' => [
                'etiqueta'  => 'Fecha de inicio',
                'tipo'      => 'date',
                'requerido' => true,
            ],
            'fecha_fin' => [
                'etiqueta'  => 'Fecha de fin',
                'tipo'      => 'date',
                'requerido' => true,
            ],
            'plan_id' => [
                'etiqueta'    => 'Plan de estudio',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'planes_estudio', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'ciclo_escolar_id' => [
                'etiqueta'    => 'Ciclo escolar',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'ciclos_escolares', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'instrumento_id' => [
                'etiqueta'    => 'Instrumento',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'instrumentos', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
        ],
    ],

    // selecciones, comentarios y respuestas las llena el sistema cuando el
    // alumno contesta. Los selects de evaluación, carga y pregunta muestran el
    // id: esas tablas no tienen una columna que sirva de nombre.
    'selecciones' => [
        'etiqueta' => 'Selecciones',
        'listar'   => ['id', 'evaluacion_id', 'carga_academica_id', 'evaluo', 'fecha'],
        'campos'   => [
            'evaluacion_id' => [
                'etiqueta'    => 'Evaluación',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'evaluaciones', 'muestra' => 'id', 'guarda' => 'id'],
            ],
            'carga_academica_id' => [
                'etiqueta'    => 'Carga académica',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'cargas_academicas', 'muestra' => 'id', 'guarda' => 'id'],
            ],
            'evaluo' => [
                'etiqueta'  => 'Evaluó',
                'tipo'      => 'select',
                'requerido' => true,
                'opciones'  => ['1', '0'],
                'etiquetas' => ['1' => 'Sí', '0' => 'No'],
                'defecto'   => '0',
            ],
            'fecha' => [
                'etiqueta' => 'Fecha',
                'tipo'     => 'date',
            ],
        ],
    ],

    'comentarios' => [
        'etiqueta' => 'Comentarios',
        'listar'   => ['id', 'evaluacion_id', 'ciclo_escolar_id', 'asignatura_id', 'profesor_id', 'clave_grupo', 'fecha'],
        'campos'   => [
            'comentario' => [
                'etiqueta'  => 'Comentario',
                'tipo'      => 'textarea',
                'requerido' => true,
            ],
            // DATETIME: texto 'AAAA-MM-DD hh:mm:ss'. datetime-local pide una T
            // en medio y no mostraría el valor guardado al editar.
            'fecha' => [
                'etiqueta' => 'Fecha',
                'tipo'     => 'text',
            ],
            'evaluacion_id' => [
                'etiqueta'    => 'Evaluación',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'evaluaciones', 'muestra' => 'id', 'guarda' => 'id'],
            ],
            'ciclo_escolar_id' => [
                'etiqueta'    => 'Ciclo escolar',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'ciclos_escolares', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'asignatura_id' => [
                'etiqueta'    => 'Asignatura',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'asignaturas', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'profesor_id' => [
                'etiqueta'    => 'Profesor (clave)',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'profesores', 'muestra' => 'clave', 'guarda' => 'id'],
            ],
            'clave_grupo' => [
                'etiqueta' => 'Clave de grupo',
                'tipo'     => 'text',
            ],
        ],
    ],

    'respuestas' => [
        'etiqueta' => 'Respuestas',
        'listar'   => ['id', 'evaluacion_id', 'pregunta_id', 'respuesta', 'profesor_id', 'clave_grupo', 'fecha'],
        'campos'   => [
            'pregunta_id' => [
                'etiqueta'    => 'Pregunta',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'preguntas', 'muestra' => 'id', 'guarda' => 'id'],
            ],
            'respuesta' => [
                'etiqueta' => 'Respuesta',
                'tipo'     => 'textarea',
            ],
            // Igual que en comentarios: DATETIME como texto.
            'fecha' => [
                'etiqueta' => 'Fecha',
                'tipo'     => 'text',
            ],
            'evaluacion_id' => [
                'etiqueta'    => 'Evaluación',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'evaluaciones', 'muestra' => 'id', 'guarda' => 'id'],
            ],
            'ciclo_escolar_id' => [
                'etiqueta'    => 'Ciclo escolar',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'ciclos_escolares', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'asignatura_id' => [
                'etiqueta'    => 'Asignatura',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'asignaturas', 'muestra' => 'nombre', 'guarda' => 'id'],
            ],
            'profesor_id' => [
                'etiqueta'    => 'Profesor (clave)',
                'tipo'        => 'select',
                'requerido'   => true,
                'opciones_de' => ['tabla' => 'profesores', 'muestra' => 'clave', 'guarda' => 'id'],
            ],
            'clave_grupo' => [
                'etiqueta' => 'Clave de grupo',
                'tipo'     => 'text',
            ],
        ],
    ],
];
