-- Datos de prueba para evaluacion_docente
USE evaluacion_docente;

-- Cuentas locales de prueba (mientras no se retoma el login con Directorio Activo).
-- password_hash generado con password_hash($plano, PASSWORD_DEFAULT):
--   alumno1  / Alumno123!
--   docente1 / Docente123!
INSERT INTO usuarios (usuario, nombre, correo, password_hash, rol) VALUES
    ('alumno1', 'Alumno de Prueba', 'alumno1@evaluaciondocente.local', '$2y$10$weJZQLbx5jDL/wFawdzU9uohaxgYXpSq2NCS1LexS2/gx8whUweqa', 'alumno'),
    ('docente1', 'Docente de Prueba', 'docente1@evaluaciondocente.local', '$2y$10$/sbf3KpCJk7S7mwvWKIw1OMqYE/FqztrsG.JFZvaiSycwQq3B5CCW', 'docente')
ON DUPLICATE KEY UPDATE usuario = usuario;
