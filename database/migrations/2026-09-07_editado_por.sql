-- Migración: columna `editado_por` en las tablas de entidad
--
-- Guarda el nombre de usuario (usuarios.usuario) de quien creó o modificó el
-- registro desde el sistema. NULL = no lo tocó nadie desde la interfaz
-- (seeder, migración, mano a la base).
--
-- Es texto y no una llave foránea a usuarios(id) a propósito: un rastro de
-- auditoría tiene que sobrevivir al borrado del usuario. Con FK, dar de baja a
-- alguien borraría también la constancia de lo que editó.
--
-- Las tablas puente (usuario_grupo, grupo_modulo) quedan fuera: no tienen id
-- propio y se reescriben enteras en cada guardado, así que la columna no diría
-- quién asignó qué.
--
-- Para una base que ya tiene datos. Una instalación nueva no la necesita:
-- database/schema.sql ya trae la columna.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-09-07_editado_por.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

ALTER TABLE usuarios ADD COLUMN editado_por VARCHAR(32) NULL DEFAULT NULL;
ALTER TABLE grupos   ADD COLUMN editado_por VARCHAR(32) NULL DEFAULT NULL;
ALTER TABLE modulos  ADD COLUMN editado_por VARCHAR(32) NULL DEFAULT NULL;
