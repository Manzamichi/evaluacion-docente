-- Migración: usuarios sin nombre, con estado; tabla `alumnos`
--
-- usuarios.nombre    se elimina. Sus datos se pierden: respáldalos antes si
--                    hacen falta.
-- usuarios.usuario   pasa a VARCHAR(50), y `editado_por` de todas las tablas
--                    con él, porque guarda ese mismo valor.
-- usuarios.estado    nuevo: 1 = activo, 0 = inactivo. Los que ya existen
--                    quedan activos.
-- alumnos            nueva: matrícula y el usuario con el que entra.
--
-- Una instalación nueva no la necesita: database/schema.sql ya trae los cambios.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-10-21_alumnos.sql
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/seed.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

ALTER TABLE usuarios
    DROP COLUMN nombre,
    MODIFY usuario VARCHAR(50) NOT NULL,
    ADD COLUMN estado TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash,
    MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;

ALTER TABLE grupos         MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE categorias     MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE modulos        MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE tipo_preguntas MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE instrumentos   MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE dimensiones    MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE preguntas      MODIFY editado_por VARCHAR(50) NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS alumnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(8) NOT NULL UNIQUE,
    usuario_id INT NOT NULL UNIQUE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
