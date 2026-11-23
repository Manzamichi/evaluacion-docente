-- Migración: módulos de las tablas académicas
--
-- Registra en el menú las pantallas CRUD de las tablas que agregó
-- 2026-11-18_tablas_academicas.sql (correr esa primero). El pivote
-- coordinador_plan no lleva pantalla propia.
--
-- Una instalación nueva no la necesita: database/seed.sql ya trae estas filas.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-11-23_modulos_academicos.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

INSERT INTO categorias (nombre, orden) VALUES
    ('Académico', 30),
    ('Evaluación', 40)
ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO modulos (nombre, descripcion, url, categoria, orden) VALUES
    ('Planes de estudio', 'Planes y sus versiones',   'plan/admin',        'Académico', 10),
    ('Ciclos escolares', 'Semestres y el ciclo activo', 'ciclo/admin',     'Académico', 20),
    ('Asignaturas',  'Materias de cada plan',         'asignatura/admin',  'Académico', 30),
    ('Coordinadores', 'Cargos de coordinación',       'coordinador/admin', 'Académico', 40),
    ('Cargas académicas', 'Alumno, asignatura y profesor por ciclo', 'carga/admin', 'Académico', 50),
    ('Evaluaciones', 'Periodos de evaluación',        'evaluacion/admin',  'Evaluación', 10),
    ('Selecciones',  'Quién ya evaluó cada carga',    'seleccion/admin',   'Evaluación', 20),
    ('Comentarios',  'Comentarios anónimos',          'comentario/admin',  'Evaluación', 30),
    ('Respuestas',   'Respuestas anónimas',           'respuesta/admin',   'Evaluación', 40)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), categoria = VALUES(categoria), orden = VALUES(orden);
