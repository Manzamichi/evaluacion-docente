-- Migración: tabla `categorias` para el menú lateral
--
-- Antes `modulos.categoria` era texto libre. Ahora es una FK al nombre de una
-- fila de `categorias`, que se administra desde Módulos > Categorías.
--
-- Se crean las categorías a partir de las que ya usan los módulos, así ningún
-- módulo queda huérfano al agregar la FK.
--
-- Para una base que ya tiene datos. Una instalación nueva no la necesita:
-- database/schema.sql ya trae la tabla. Después de correrla, corre también
-- database/seed.sql para dar de alta el módulo `categoria/admin`.
--
--   docker exec -i evaluacion_docente_db mysql -uapp_user -papp_password \
--       evaluacion_docente < database/migrations/2026-09-14_categorias.sql

SET NAMES utf8mb4;

USE evaluacion_docente;

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40) NOT NULL UNIQUE,
    orden INT NOT NULL DEFAULT 0,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    editado_por VARCHAR(32) NULL DEFAULT NULL
);

INSERT IGNORE INTO categorias (nombre)
SELECT DISTINCT categoria FROM modulos;

ALTER TABLE modulos
    ALTER COLUMN categoria DROP DEFAULT,
    ADD FOREIGN KEY (categoria) REFERENCES categorias(nombre)
        ON UPDATE CASCADE ON DELETE RESTRICT;
