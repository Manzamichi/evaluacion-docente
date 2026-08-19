<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/validation.php';
require_once __DIR__ . '/../src/helpers/ldap_auth.php';

$errores = [];
$matriculaEnviada = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matriculaEnviada = trim((string) ($_POST['matricula'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!validarMatricula($matriculaEnviada)) {
        $errores[] = 'La matrícula debe tener el formato a99999999 (una letra "a" seguida de 8 números).';
    }

    if (!validarPasswordComplejidad($password)) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres y cumplir 3 de las 4 reglas de complejidad.';
    }

    if (empty($errores) && !autenticarContraAD($matriculaEnviada, $password)) {
        $errores[] = 'Matrícula o contraseña incorrectas.';
    }

    if (empty($errores)) {
        $correo = construirCorreoInstitucional($matriculaEnviada);
        $pdo = getDbConnection();

        $stmt = $pdo->prepare('SELECT id, nombre, correo, rol FROM usuarios WHERE correo = :correo');
        $stmt->execute(['correo' => $correo]);
        $usuario = $stmt->fetch();

        if ($usuario === false) {
            /* El AD ya confirmó la identidad; aquí solo damos de alta el perfil/rol local.
             password_hash no se usa para autenticar (la contraseña real vive en el AD),
             se llena con un valor aleatorio porque la columna es NOT NULL.¨*/
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nombre, correo, password_hash, rol) VALUES (:nombre, :correo, :hash, :rol)'
            );
            $stmt->execute([
                'nombre' => strtoupper($matriculaEnviada),
                'correo' => $correo,
                'hash'   => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'rol'    => 'alumno',
            ]);

            $usuario = [
                'id'     => (int) $pdo->lastInsertId(),
                'nombre' => strtoupper($matriculaEnviada),
                'correo' => $correo,
                'rol'    => 'alumno',
            ];
        }

        $_SESSION['usuario'] = [
            'id'     => (int) $usuario['id'],
            'nombre' => $usuario['nombre'],
            'correo' => $usuario['correo'],
            'rol'    => $usuario['rol'],
        ];

        header('Location: index.php');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - evaluacion_docente</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="login-box">
        <h1>Iniciar sesión</h1>
        <p class="login-hint">Usa tu cuenta institucional (matrícula + contraseña de INET).</p>

        <?php if (!empty($errores)): ?>
            <ul class="login-errores">
                <?php foreach ($errores as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <label for="matricula">Matrícula</label>
            <input
                type="text"
                id="matricula"
                name="matricula"
                placeholder="a22245245"
                value="<?= htmlspecialchars($matriculaEnviada, ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Entrar</button>
        </form>
    </main>
    <script src="assets/js/app.js"></script>
</body>
</html>
