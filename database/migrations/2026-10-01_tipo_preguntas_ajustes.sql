-- Migración: ajustes a `tipo_preguntas`
--
-- tipo      no admite negativos: pasa a TINYINT UNSIGNED (0 a 255).
-- respuesta pasa a NULL: un tipo "instrucciones" no espera respuesta.
--
-- Falla si ya hay algún `tipo` negativo; corrígelo antes de correrla.
-- Una instalación nueva no la necesita: database/schema.sql ya trae los cambios.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-10-01_tipo_preguntas_ajustes.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

ALTER TABLE tipo_preguntas
    MODIFY tipo TINYINT UNSIGNED NOT NULL,
    MODIFY respuesta VARCHAR(2048) NULL;
