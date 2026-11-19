-- Migración: tipo de usuario, auditoría completa y estructura académica
--
-- 1. usuarios.tipo (0 = otro, 1 = profesor, 2 = alumno), rellenado a partir de
--    las tablas profesores y alumnos.
-- 2. creado_por y editado_en en todas las tablas salvo las pivote; alumnos,
--    que no tenía auditoría, recibe las cuatro columnas.
-- 3. Tablas del sistema original sin el prefijo tbl_: planes_estudio,
--    ciclos_escolares, asignaturas, coordinadores, coordinador_plan,
--    cargas_academicas, evaluaciones, selecciones, comentarios, respuestas.
--
-- Los registros que ya existen quedan con creado_por y editado_en en NULL.
--
-- Una instalación nueva no la necesita: database/schema.sql ya trae los cambios.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-11-18_tablas_academicas.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

-- 1. Tipo de usuario

ALTER TABLE usuarios
    ADD COLUMN tipo TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER estado;

UPDATE usuarios u JOIN profesores p ON p.usuario_id = u.id SET u.tipo = 1;
UPDATE usuarios u JOIN alumnos a ON a.usuario_id = u.id SET u.tipo = 2;

-- 2. Auditoría

ALTER TABLE usuarios
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE grupos
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE categorias
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE modulos
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE profesores
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE tipo_preguntas
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE instrumentos
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE dimensiones
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE preguntas
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL AFTER creado_en,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER creado_por;

ALTER TABLE alumnos
    ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN creado_por VARCHAR(50) NULL DEFAULT NULL,
    ADD COLUMN editado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN editado_por VARCHAR(50) NULL DEFAULT NULL;

-- 3. Estructura académica

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
    fecha DATETIME NULL,
    ciclo_escolar_id INT NOT NULL,
    asignatura_id INT NOT NULL,
    profesor_id INT UNSIGNED NOT NULL,
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
    fecha DATETIME NULL,
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
