-- Esquema inicial de evaluacion_docente

CREATE DATABASE IF NOT EXISTS evaluacion docente
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE evaluacion docente;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'alumno', 'docente') NOT NULL DEFAULT 'alumno',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
