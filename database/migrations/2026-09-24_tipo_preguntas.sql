SET NAMES utf8mb4;

USE evaluacion_docente;

CREATE TABLE IF NOT EXISTS tipo_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    tipo TINYINT NOT NULL,
    respuesta VARCHAR(2048) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS instrumentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    fecha_elaboracion DATE NOT NULL,
    instruccion TEXT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS dimensiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    comentarios VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

-- Va al final: sus FK necesitan que las tres tablas anteriores ya existan.
-- RESTRICT: no se puede borrar un instrumento, tipo o dimensión que tenga preguntas.
CREATE TABLE IF NOT EXISTS preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instrumento_id INT NOT NULL,
    tipo_id INT NOT NULL,
    pregunta TEXT NOT NULL,
    dimension_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL,
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (tipo_id) REFERENCES tipo_preguntas(id) ON DELETE RESTRICT,
    FOREIGN KEY (dimension_id) REFERENCES dimensiones(id) ON DELETE RESTRICT
);