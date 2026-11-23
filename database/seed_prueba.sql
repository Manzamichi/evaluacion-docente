-- Datos de prueba de las tablas académicas, solo para ver cómo se ven los CRUD.
-- Los datos no son coherentes entre sí a propósito.
--
-- No va en docker-entrypoint-initdb.d: se carga a mano, DESPUÉS de seed.sql.
-- Correrlo una sola vez: repetirlo duplica cargas, evaluaciones, comentarios
-- y respuestas (las demás tablas se protegen solas con INSERT IGNORE / NOT EXISTS).
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/seed_prueba.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

INSERT INTO planes_estudio (nombre, siglas, clave, version, fecha_inicio, comentarios, creado_por, editado_por)
SELECT * FROM (
    SELECT 'Licenciatura en Ingeniería de Software' AS nombre, 'LIS' AS siglas, 'LIS2020' AS clave, '2020' AS version,
           DATE '2020-08-01' AS fecha_inicio, 'Plan de prueba' AS comentarios, 'seed' AS creado_por, 'seed' AS editado_por
    UNION ALL SELECT 'Licenciatura en Ciencias de la Computación', 'LCC', 'LCC2018', '2018', DATE '2018-08-01', NULL, 'seed', 'seed'
    UNION ALL SELECT 'Licenciatura en Matemáticas', 'LMA', 'LMA2022', '2022', DATE '2022-08-01', 'Plan de prueba', 'seed', 'seed'
) t
WHERE NOT EXISTS (SELECT 1 FROM planes_estudio);

INSERT INTO ciclos_escolares (nombre, activo, orden, comentarios, creado_por, editado_por)
SELECT * FROM (
    SELECT 'Enero-Junio 2026' AS nombre, 0 AS activo, 1 AS orden, NULL AS comentarios, 'seed' AS creado_por, 'seed' AS editado_por
    UNION ALL SELECT 'Agosto-Diciembre 2026', 1, 2, 'Ciclo en curso', 'seed', 'seed'
    UNION ALL SELECT 'Enero-Junio 2027', 0, 3, NULL, 'seed', 'seed'
) t
WHERE NOT EXISTS (SELECT 1 FROM ciclos_escolares);

INSERT INTO asignaturas (clave, nombre, comentarios, plan_id, creado_por, editado_por)
SELECT t.clave, t.nombre, t.comentarios, p.id, 'seed', 'seed'
FROM (
    SELECT 'LIS101' AS clave, 'Programación I' AS nombre, NULL AS comentarios, 'LIS2020' AS plan
    UNION ALL SELECT 'LIS102', 'Bases de datos', 'Obligatoria', 'LIS2020'
    UNION ALL SELECT 'LIS201', 'Ingeniería de requisitos', NULL, 'LIS2020'
    UNION ALL SELECT 'LCC101', 'Estructuras de datos', NULL, 'LCC2018'
    UNION ALL SELECT 'LCC102', 'Sistemas operativos', 'Optativa', 'LCC2018'
    UNION ALL SELECT 'LMA101', 'Cálculo diferencial', NULL, 'LMA2022'
) t
JOIN planes_estudio p ON p.clave = t.plan
WHERE NOT EXISTS (SELECT 1 FROM asignaturas);

INSERT INTO coordinadores (nombre, profesor_id, creado_por, editado_por)
SELECT t.nombre, pr.id, 'seed', 'seed'
FROM (
    SELECT 'Coordinador de la Licenciatura en Ingeniería de Software' AS nombre
    UNION ALL SELECT 'Coordinador de la Licenciatura en Ciencias de la Computación'
) t
JOIN profesores pr ON pr.clave = 'P0001'
WHERE NOT EXISTS (SELECT 1 FROM coordinadores);

-- Pivote: sin pantalla propia, pero se llena para la fase de asignación.
INSERT IGNORE INTO coordinador_plan (coordinador_id, plan_id)
SELECT c.id, p.id
FROM coordinadores c
JOIN planes_estudio p ON (c.nombre LIKE '%Software%' AND p.clave = 'LIS2020')
                      OR (c.nombre LIKE '%Computación%' AND p.clave = 'LCC2018');

INSERT INTO cargas_academicas (ciclo_escolar_id, asignatura_id, profesor_id, matricula, clave_grupo, grupo, creado_por, editado_por)
SELECT ci.id, a.id, pr.id, t.matricula, t.clave_grupo, t.grupo, 'seed', 'seed'
FROM (
    SELECT '20000001' AS matricula, 'LIS101' AS asig, 'A1' AS clave_grupo, 'A' AS grupo
    UNION ALL SELECT '20000002', 'LIS101', 'A1', 'A'
    UNION ALL SELECT '20000003', 'LIS102', 'B1', 'B'
    UNION ALL SELECT '20000001', 'LIS102', 'B1', 'B'
    UNION ALL SELECT '20000004', 'LCC101', 'C1', 'C'
    UNION ALL SELECT '20000005', 'LMA101', 'D1', 'D'
) t
JOIN asignaturas a ON a.clave = t.asig
JOIN ciclos_escolares ci ON ci.activo = 1
JOIN profesores pr ON pr.clave = 'P0001'
WHERE NOT EXISTS (SELECT 1 FROM cargas_academicas);

INSERT INTO evaluaciones (fecha_inicio, fecha_fin, plan_id, ciclo_escolar_id, instrumento_id, creado_por, editado_por)
SELECT t.inicio, t.fin, p.id, ci.id, i.id, 'seed', 'seed'
FROM (
    SELECT DATE '2026-11-01' AS inicio, DATE '2026-11-30' AS fin, 'LIS2020' AS plan
    UNION ALL SELECT DATE '2026-11-10', DATE '2026-12-05', 'LCC2018'
    UNION ALL SELECT DATE '2026-11-15', DATE '2026-12-10', 'LMA2022'
) t
JOIN planes_estudio p ON p.clave = t.plan
JOIN ciclos_escolares ci ON ci.activo = 1
JOIN instrumentos i ON i.id = (SELECT MIN(id) FROM instrumentos)
WHERE NOT EXISTS (SELECT 1 FROM evaluaciones);

-- Una selección por carga de la primera evaluación; las dos primeras ya evaluaron.
INSERT IGNORE INTO selecciones (evaluacion_id, carga_academica_id, evaluo, fecha, creado_por, editado_por)
SELECT (SELECT MIN(id) FROM evaluaciones), c.id,
       IF(c.id IN (SELECT id FROM (SELECT id FROM cargas_academicas ORDER BY id LIMIT 2) x), 1, 0),
       IF(c.id IN (SELECT id FROM (SELECT id FROM cargas_academicas ORDER BY id LIMIT 2) x), DATE '2026-11-12', NULL),
       'seed', 'seed'
FROM cargas_academicas c
WHERE EXISTS (SELECT 1 FROM evaluaciones);

INSERT INTO comentarios (comentario, fecha, ciclo_escolar_id, asignatura_id, profesor_id, clave_grupo, evaluacion_id, creado_por, editado_por)
SELECT t.comentario, t.fecha, ci.id, a.id, pr.id, t.clave_grupo, e.id, 'seed', 'seed'
FROM (
    SELECT 'Explica con claridad.' AS comentario, '2026-11-12 10:15:00' AS fecha, 'LIS101' AS asig, 'A1' AS clave_grupo
    UNION ALL SELECT 'Podría dejar menos tareas.', '2026-11-12 11:40:00', 'LIS101', 'A1'
    UNION ALL SELECT 'Siempre llega puntual.', '2026-11-13 09:05:00', 'LIS102', 'B1'
    UNION ALL SELECT 'Las clases son muy rápidas.', '2026-11-14 16:30:00', 'LCC101', 'C1'
) t
JOIN asignaturas a ON a.clave = t.asig
JOIN ciclos_escolares ci ON ci.activo = 1
JOIN profesores pr ON pr.clave = 'P0001'
JOIN evaluaciones e ON e.id = (SELECT MIN(id) FROM evaluaciones);

INSERT INTO respuestas (pregunta_id, respuesta, fecha, ciclo_escolar_id, asignatura_id, profesor_id, clave_grupo, evaluacion_id, creado_por, editado_por)
SELECT q.id, t.respuesta, t.fecha, ci.id, a.id, pr.id, t.clave_grupo, e.id, 'seed', 'seed'
FROM (
    SELECT '1' AS respuesta, '2026-11-12 10:15:00' AS fecha, 'LIS101' AS asig, 'A1' AS clave_grupo
    UNION ALL SELECT '2', '2026-11-12 11:40:00', 'LIS101', 'A1'
    UNION ALL SELECT '1', '2026-11-13 09:05:00', 'LIS102', 'B1'
) t
JOIN preguntas q
JOIN asignaturas a ON a.clave = t.asig
JOIN ciclos_escolares ci ON ci.activo = 1
JOIN profesores pr ON pr.clave = 'P0001'
JOIN evaluaciones e ON e.id = (SELECT MIN(id) FROM evaluaciones);
