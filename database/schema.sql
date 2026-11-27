-- Esquema de evaluacion_docente
--
-- Control de acceso:
--   usuarios  --N:N-->  grupos  --N:N-->  modulos
--
--   modulo = una pantalla del sistema. Su columna `url` es la llave que el
--            front controller busca en src/modules.php para saber qué código
--            ejecutar. Un módulo sin entrada ahí existe en el menú pero no se
--            puede abrir (404), nunca se incluye un archivo a partir de la BD.
--   grupo  = un rol. Un usuario puede tener varios (profesor + admin).
--            es_admin = 1 salta la revisión: ve todos los módulos.
--
-- Auditoría: toda tabla, salvo las pivote, lleva cuatro columnas.
--   creado_en   = cuándo se insertó. Lo pone MySQL.
--   creado_por  = usuarios.usuario de quien lo creó desde el sistema. Lo llena
--                 crud.php en el INSERT y ya no cambia.
--   editado_en  = última modificación. Lo pone MySQL (ON UPDATE); NULL = nunca
--                 se ha editado.
--   editado_por = usuarios.usuario del último que lo creó o modificó. Lo llena
--                 crud.php en cada INSERT/UPDATE.
-- Un *_por NULL significa que no pasó por la interfaz: seeder, migración o mano
-- a la base. Son texto y no FK a propósito: el rastro de auditoría debe
-- sobrevivir al borrado del usuario que lo dejó.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS evaluacion_docente
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE evaluacion_docente;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    -- 1 = activo, 0 = inactivo. Un usuario inactivo no puede iniciar sesión.
    estado TINYINT(1) NOT NULL DEFAULT 1,
    -- 0 = otro (administrativo), 1 = profesor, 2 = alumno. Viene del sistema
    -- original; lo ponen las pantallas de profesores y alumnos al dar de alta.
    tipo TINYINT UNSIGNED NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    es_admin TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL
);

-- Categorías del menú lateral. `orden` decide en qué posición sale cada
-- encabezado; los módulos se ordenan dentro con su propio `orden`.
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40) NOT NULL UNIQUE,
    orden INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL
);

-- modulos.categoria referencia el nombre y no el id a propósito: el menú y el
-- dual box de permisos lo leen directo sin JOIN, renombrar una categoría
-- arrastra a sus módulos (ON UPDATE CASCADE) y no se puede borrar una que
-- todavía tenga módulos (RESTRICT).
CREATE TABLE IF NOT EXISTS modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    url VARCHAR(120) NOT NULL UNIQUE,
    categoria VARCHAR(40) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (categoria) REFERENCES categorias(nombre)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS usuario_grupo (
    usuario_id INT NOT NULL,
    grupo_id INT NOT NULL,
    PRIMARY KEY (usuario_id, grupo_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
);

-- Un alumno es un usuario con matrícula: alumnos --1:1--> usuarios. Borrar el
-- usuario borra al alumno (CASCADE); el módulo de alumnos borra al usuario.
CREATE TABLE IF NOT EXISTS alumnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(8) NOT NULL UNIQUE,
    usuario_id INT NOT NULL UNIQUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS grupo_modulo (
    grupo_id INT NOT NULL,
    modulo_id INT NOT NULL,
    PRIMARY KEY (grupo_id, modulo_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE
);

-- Profesores. Cada profesor tiene exactamente un usuario (1:1); el usuario se
-- crea desde la pantalla del profesor, no desde un módulo aparte. RESTRICT:
-- no se borra un usuario que sigue ligado a un profesor.
-- La clave es la clave proveniente del sicei
CREATE TABLE IF NOT EXISTS profesores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(10) NOT NULL UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellido_paterno VARCHAR(50) NOT NULL,
    apellido_materno VARCHAR(50) NOT NULL,
    grado VARCHAR(20) NULL,
    correo VARCHAR(100) NOT NULL,
    usuario_id INT NOT NULL UNIQUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
);

-- Instrumentos de evaluación
--
--   instrumento  --1:N-->  preguntas  <--N:1--  dimensiones
--                              ^
--                              N:1
--                         tipo_preguntas
--
-- Las FK de preguntas son RESTRICT: no se puede borrar un instrumento, tipo o
-- dimensión mientras alguna pregunta lo use.

CREATE TABLE IF NOT EXISTS tipo_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    tipo TINYINT UNSIGNED NOT NULL,
    -- NULL en los tipos que no esperan respuesta (instrucciones).
    respuesta VARCHAR(2048) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS instrumentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    fecha_elaboracion DATE NOT NULL,
    instruccion TEXT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS dimensiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    comentarios VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT NOT NULL,
    tipo_id INT NOT NULL,
    pregunta TEXT NOT NULL,
    dimension_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (tipo_id) REFERENCES tipo_preguntas(id) ON DELETE RESTRICT,
    FOREIGN KEY (dimension_id) REFERENCES dimensiones(id) ON DELETE RESTRICT
);

-- Estructura académica (sistema original)
--
--   planes_estudio  --1:N-->  asignaturas
--         ^ N:N (coordinador_plan)
--   coordinadores  --N:1-->  profesores
--
--   cargas_academicas = ciclo + asignatura + profesor + grupo, una fila por
--                       alumno inscrito (matricula). La matrícula es texto y no
--                       FK a propósito: se importa del sistema escolar antes de
--                       que el alumno tenga cuenta.
--
-- profesor_id es INT UNSIGNED porque profesores.id lo es; si no, la FK falla.
-- Todas las FK son RESTRICT salvo el pivote, igual que en instrumentos.

CREATE TABLE IF NOT EXISTS planes_estudio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    siglas VARCHAR(10) NULL,
    clave VARCHAR(10) NULL,
    version VARCHAR(10) NULL,
    fecha_inicio DATE NULL,
    comentarios VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL
);

-- activo = 1 marca el ciclo (semestre) en curso; en el original se llamaba
-- `actual`. Que solo haya uno lo cuida la aplicación.
-- orden = posición cronológica, crece de 1 en 1.
CREATE TABLE IF NOT EXISTS ciclos_escolares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    comentarios VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS asignaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(15) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    comentarios VARCHAR(255) NULL,
    plan_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (plan_id) REFERENCES planes_estudio(id) ON DELETE RESTRICT
);

-- nombre = el cargo ("Coordinador de la Licenciatura en ..."), no la persona.
CREATE TABLE IF NOT EXISTS coordinadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    profesor_id INT UNSIGNED NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (profesor_id) REFERENCES profesores(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS coordinador_plan (
    coordinador_id INT NOT NULL,
    plan_id INT NOT NULL,
    PRIMARY KEY (coordinador_id, plan_id),
    FOREIGN KEY (coordinador_id) REFERENCES coordinadores(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES planes_estudio(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cargas_academicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ciclo_escolar_id INT NOT NULL,
    asignatura_id INT NOT NULL,
    profesor_id INT UNSIGNED NOT NULL,
    matricula VARCHAR(8) NOT NULL,
    clave_grupo VARCHAR(10) NULL,
    grupo VARCHAR(10) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    INDEX (matricula),
    FOREIGN KEY (ciclo_escolar_id) REFERENCES ciclos_escolares(id) ON DELETE RESTRICT,
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE RESTRICT,
    FOREIGN KEY (profesor_id) REFERENCES profesores(id) ON DELETE RESTRICT
);

-- Periodo en que un plan aplica un instrumento en un ciclo.
CREATE TABLE IF NOT EXISTS evaluaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    plan_id INT NOT NULL,
    ciclo_escolar_id INT NOT NULL,
    instrumento_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (plan_id) REFERENCES planes_estudio(id) ON DELETE RESTRICT,
    FOREIGN KEY (ciclo_escolar_id) REFERENCES ciclos_escolares(id) ON DELETE RESTRICT,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id) ON DELETE RESTRICT
);

-- Una fila por carga académica en cada evaluación: evaluo = 1 cuando el
-- alumno ya contestó. No guarda qué contestó (eso va anónimo en respuestas).
CREATE TABLE IF NOT EXISTS selecciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluacion_id INT NOT NULL,
    carga_academica_id INT NOT NULL,
    evaluo TINYINT(1) NOT NULL DEFAULT 0,
    fecha DATE NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    UNIQUE (evaluacion_id, carga_academica_id),
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE RESTRICT,
    FOREIGN KEY (carga_academica_id) REFERENCES cargas_academicas(id) ON DELETE RESTRICT
);

-- comentarios y respuestas copian ciclo/asignatura/profesor/grupo en lugar de
-- apuntar a la carga académica: así no quedan ligadas a la matrícula y la
-- evaluación es anónima.
CREATE TABLE IF NOT EXISTS comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comentario TEXT NOT NULL,
    fecha DATE NULL,
    -- ¿Es necesario?  En el original no lo pide
    ciclo_escolar_id INT NOT NULL,
    asignatura_id INT NOT NULL,
    profesor_id INT UNSIGNED NOT NULL,
    -- ¿Es necesario?  En el original no lo pide
    clave_grupo VARCHAR(10) NULL,
    evaluacion_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (ciclo_escolar_id) REFERENCES ciclos_escolares(id) ON DELETE RESTRICT,
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE RESTRICT,
    FOREIGN KEY (profesor_id) REFERENCES profesores(id) ON DELETE RESTRICT,
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    respuesta VARCHAR(2048) NULL,
    fecha DATE NULL,
    ciclo_escolar_id INT NOT NULL,
    asignatura_id INT NOT NULL,
    profesor_id INT UNSIGNED NOT NULL,
    clave_grupo VARCHAR(10) NULL,
    evaluacion_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por VARCHAR(50) NULL DEFAULT NULL,
    editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    editado_por VARCHAR(50) NULL DEFAULT NULL,
    FOREIGN KEY (pregunta_id) REFERENCES preguntas(id) ON DELETE RESTRICT,
    FOREIGN KEY (ciclo_escolar_id) REFERENCES ciclos_escolares(id) ON DELETE RESTRICT,
    FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id) ON DELETE RESTRICT,
    FOREIGN KEY (profesor_id) REFERENCES profesores(id) ON DELETE RESTRICT,
    FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones(id) ON DELETE RESTRICT
);
