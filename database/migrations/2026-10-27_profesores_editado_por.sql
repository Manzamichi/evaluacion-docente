-- Migración: profesores.editado_por a VARCHAR(50)
--
-- 2026-10-21_alumnos.sql amplió usuarios.usuario a 50 y, con él, el
-- `editado_por` de todas las tablas, menos el de profesores. Sin esto, un
-- usuario de más de 32 caracteres no puede guardar un profesor.
--
-- Una instalación nueva no la necesita: database/schema.sql ya trae los cambios.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-10-27_profesores_editado_por.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

ALTER TABLE profesores MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;
