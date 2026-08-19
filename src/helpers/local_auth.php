<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function autenticarLocal(string $usuario, string $password): array|false
{
    $pdo = getDbConnection();

    $stmt = $pdo->prepare('SELECT id, usuario, nombre, correo, password_hash, rol FROM usuarios WHERE usuario = :usuario');
    $stmt->execute(['usuario' => trim($usuario)]);
    $fila = $stmt->fetch();

    // password_verify recalcula el hash con el mismo salt/costo guardados dentro de $fila['password_hash']
    // y lo compara en tiempo constante; nunca se compara el password en texto plano contra nada.
    if ($fila === false || !password_verify($password, $fila['password_hash'])) {
        return false;
    }

    unset($fila['password_hash']);

    return $fila;
}
