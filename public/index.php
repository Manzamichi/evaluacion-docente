<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers/auth_guard.php';

requerirSesion();

$usuario = $_SESSION['usuario'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>evaluacion_docente</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <h1>evaluacion_docente</h1>
    <p>
        Sesión iniciada como <strong><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
        (<?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?>).
        <a href="logout.php">Cerrar sesión</a>
    </p>
    <script src="assets/js/app.js"></script>
</body>
</html>
