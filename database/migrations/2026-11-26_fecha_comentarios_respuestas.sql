-- Migración: comentarios.fecha y respuestas.fecha a DATE
--
-- Los formularios piden solo el día (<input type="date">), así que la hora de
-- un DATETIME se llenaba sola con 00:00:00 y se sobrescribía al editar. El
-- momento exacto sigue en `creado_en`. selecciones.fecha ya era DATE.
--
-- Las filas que ya existen pierden la hora y conservan el día.
--
-- Una instalación nueva no la necesita: database/schema.sql ya trae los cambios.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-11-26_fecha_comentarios_respuestas.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

ALTER TABLE comentarios MODIFY fecha DATE NULL;
ALTER TABLE respuestas MODIFY fecha DATE NULL;
