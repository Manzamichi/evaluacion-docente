-- Migración: tabla `profesores` y `usuarios.activo`
--
-- profesores: cada profesor tiene exactamente un usuario (1:1, usuario_id
--             UNIQUE). El usuario se crea desde la pantalla del profesor.
-- activo:     estado del acceso (1 activo, 0 inactivo). Los usuarios
--             existentes quedan activos.
--
-- Una instalación nueva no la necesita: database/schema.sql ya trae los cambios.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-10-20_profesores.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

ALTER TABLE usuarios
    ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash;

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
    editado_por VARCHAR(32) NULL DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
);
