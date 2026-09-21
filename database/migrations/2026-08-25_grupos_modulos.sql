-- Migración: usuarios.rol (ENUM) -> grupos y módulos (N:N)
--
-- Para una base de datos que ya tiene datos. Una instalación nueva no la
-- necesita: database/schema.sql ya trae la estructura final.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-08-25_grupos_modulos.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    es_admin TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    url VARCHAR(120) NOT NULL UNIQUE,
    categoria VARCHAR(40) NOT NULL DEFAULT 'General',
    orden INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuario_grupo (
    usuario_id INT NOT NULL,
    grupo_id INT NOT NULL,
    PRIMARY KEY (usuario_id, grupo_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS grupo_modulo (
    grupo_id INT NOT NULL,
    modulo_id INT NOT NULL,
    PRIMARY KEY (grupo_id, modulo_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE
);

-- Un grupo por cada rol que existía en el ENUM.
INSERT INTO grupos (nombre, descripcion, es_admin) VALUES
    ('Administrador', 'Acceso total al sistema', 1),
    ('Profesor', 'Personal docente', 0),
    ('Alumno', 'Estudiantes', 0)
ON DUPLICATE KEY UPDATE nombre = nombre;

-- Cada usuario conserva su acceso: se le asigna el grupo que corresponde a su
-- rol anterior. Se ejecuta ANTES de borrar la columna.
INSERT IGNORE INTO usuario_grupo (usuario_id, grupo_id)
SELECT u.id, g.id
FROM usuarios u
JOIN grupos g ON g.nombre = CASE u.rol
    WHEN 'admin'   THEN 'Administrador'
    WHEN 'docente' THEN 'Profesor'
    WHEN 'alumno'  THEN 'Alumno'
END;

ALTER TABLE usuarios DROP COLUMN rol;
