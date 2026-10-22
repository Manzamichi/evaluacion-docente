-- Migración: módulo "Profesores" en la categoría Seguridad
--
-- La url profesor/admin ya está en src/modules.php. El grupo Administrador lo
-- ve solo (es_admin); a los demás se les da en Grupos > Permisos.
--
-- Una instalación nueva no la necesita: database/seed.sql ya trae la fila.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-10-20_modulo_profesores.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

INSERT INTO modulos (nombre, descripcion, url, categoria, orden) VALUES
    ('Profesores', 'Personal docente y su cuenta', 'profesor/admin', 'Seguridad', 15)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), categoria = VALUES(categoria), orden = VALUES(orden);
