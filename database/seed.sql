-- Datos de prueba para evaluacion_docente

-- Sin esto, un cliente mysql cuyo juego por defecto sea latin1 guarda los
-- acentos de este archivo doblemente codificados ("Comité" -> "ComitÃ©").
SET NAMES utf8mb4;

USE evaluacion_docente;

-- Cuentas locales de prueba (mientras no se retoma el login con Directorio Activo).
-- password_hash generado con password_hash($plano, PASSWORD_DEFAULT):
--   admin1   / Admin123!
--   alumno1  / Alumno123!
--   docente1 / Docente123!
INSERT INTO usuarios (usuario, nombre, correo, password_hash) VALUES
    ('admin1', 'Admin de Prueba', 'admin1@evaluaciondocente.local', '$2y$12$.IISUeAsMxnje8rRC7yQvuW6m.BAFDEiK01wkBOBeFe.ZIcRXZSPK'),
    ('alumno1', 'Alumno de Prueba', 'alumno1@evaluaciondocente.local', '$2y$10$weJZQLbx5jDL/wFawdzU9uohaxgYXpSq2NCS1LexS2/gx8whUweqa'),
    ('docente1', 'Docente de Prueba', 'docente1@evaluaciondocente.local', '$2y$10$/sbf3KpCJk7S7mwvWKIw1OMqYE/FqztrsG.JFZvaiSycwQq3B5CCW')
ON DUPLICATE KEY UPDATE usuario = usuario;

-- Grupos = roles. es_admin = 1 ve todos los módulos sin necesidad de asignarlos.
INSERT INTO grupos (nombre, descripcion, es_admin) VALUES
    ('Administrador', 'Acceso total al sistema', 1),
    ('Profesor', 'Personal docente', 0),
    ('Alumno', 'Estudiantes', 0),
    ('Comité', 'Consulta de reportes', 0)
ON DUPLICATE KEY UPDATE nombre = nombre;

-- Categorías del menú. Van antes que los módulos: modulos.categoria es FK.
INSERT INTO categorias (nombre, orden) VALUES
    ('Seguridad', 10)
ON DUPLICATE KEY UPDATE orden = VALUES(orden);

-- Módulos = pantallas. La `url` debe existir en src/modules.php para poder
-- abrirse; si no, aparece en el menú pero responde 404.
INSERT INTO modulos (nombre, descripcion, url, categoria, orden) VALUES
    ('Usuarios', 'Alta y baja de cuentas',        'usuario/admin',  'Seguridad', 10),
    ('Grupos',   'Roles y sus permisos',          'grupo/admin',    'Seguridad', 20),
    ('Módulos',  'Pantallas registradas',         'modulo/admin',   'Seguridad', 30),
    ('Categorías', 'Categorías del menú',         'categoria/admin','Seguridad', 35),
    ('Permisos del grupo', 'Módulos de un grupo', 'grupo/permisos', 'Seguridad', 40),
    ('Usuarios del grupo', 'Miembros de un grupo','grupo/usuarios', 'Seguridad', 50),
    ('Grupos del usuario', 'Roles de una cuenta', 'usuario/grupos', 'Seguridad', 60)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), categoria = VALUES(categoria), orden = VALUES(orden);

-- admin1 es Administrador; los otros dos, su grupo correspondiente.
INSERT IGNORE INTO usuario_grupo (usuario_id, grupo_id)
SELECT u.id, g.id
FROM usuarios u
JOIN grupos g ON (u.usuario, g.nombre) IN (
    ('admin1', 'Administrador'),
    ('docente1', 'Profesor'),
    ('alumno1', 'Alumno')
);
