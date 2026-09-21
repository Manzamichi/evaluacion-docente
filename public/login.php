<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers/validation.php';
require_once __DIR__ . '/../src/helpers/local_auth.php';
require_once __DIR__ . '/../src/components/_render.php';

// Login con AD (INET) parqueado por ahora: ver src/helpers/ldap_auth.php y
// validarMatricula()/validarPasswordComplejidad() en validation.php para retomarlo.

if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$errores = [];
$usuarioEnviado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioEnviado = trim((string) ($_POST['usuario'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!validarUsuarioGenerico($usuarioEnviado)) {
        $errores[] = 'El usuario debe tener entre 3 y 32 caracteres (letras, números, "." o "_").';
    }

    if (!validarPasswordGenerica($password)) {
        $errores[] = 'La contraseña debe tener entre 8 y 64 caracteres.';
    }

    if (empty($errores)) {
        $usuario = autenticarLocal($usuarioEnviado, $password);

        if ($usuario === false) {
            $errores[] = 'Usuario o contraseña incorrectos.';
        } else {
            $_SESSION['usuario'] = $usuario;
            header('Location: index.php');
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - evaluacion_docente</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(assetVer('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <header class="barra">
        <img class="barra-logo"
             src="<?= htmlspecialchars(assetVer('assets/img/logo-uady.png'), ENT_QUOTES, 'UTF-8') ?>"
             alt="Universidad Autónoma de Yucatán">

        <span class="barra-titulo">Sistema de Evaluación Docente</span>
    </header>

    <main class="login-box">
        <h1>Iniciar sesión</h1>
        <!--<p class="login-hint">Cuenta local de prueba (mientras no se retoma el login con AD).</p>-->

        <form method="post" action="login.php" novalidate>
            <label>
                <span>Usuario</span>
                <input
                    type="text"
                    name="usuario"
                    placeholder="alumno1"
                    maxlength="32"
                    autocomplete="username"
                    value="<?= htmlspecialchars($usuarioEnviado, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </label>

            <label>
                <span>Contraseña</span>
                <input type="password" name="password" maxlength="64" autocomplete="current-password" required>
            </label>

            <?php if (!empty($errores)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <button type="submit" class="boton login-boton">Entrar</button>
        </form>
    </main>
    <script src="<?= htmlspecialchars(assetVer('assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
