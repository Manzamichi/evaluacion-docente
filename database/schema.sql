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
-- editado_por = usuarios.usuario de quien creó o modificó el registro desde el
--               sistema. Lo llena crud.php en cada INSERT/UPDATE. NULL significa
--               que no pasó por la interfaz: seeder, migración o mano a la base.
--               Es texto y no una FK a propósito: el rastro de auditoría debe
--               sobrevivir al borrado del usuario que lo dejó.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS evaluacion_docente
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE evaluacion_docente;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(32) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    es_admin TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

-- Categorías del menú lateral. `orden` decide en qué posición sale cada
-- encabezado; los módulos se ordenan dentro con su propio `orden`.
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40) NOT NULL UNIQUE,
    orden INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
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
    editado_por VARCHAR(32) NULL DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS grupo_modulo (
    grupo_id INT NOT NULL,
    modulo_id INT NOT NULL,
    PRIMARY KEY (grupo_id, modulo_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE
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
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS instrumentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    fecha_elaboracion DATE NOT NULL,
    instruccion TEXT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS dimensiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    comentarios VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT NOT NULL,
    tipo_id INT NOT NULL,
    pregunta TEXT NOT NULL,
    dimension_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (tipo_id) REFERENCES tipo_preguntas(id) ON DELETE RESTRICT,
    FOREIGN KEY (dimension_id) REFERENCES dimensiones(id) ON DELETE RESTRICT
);
