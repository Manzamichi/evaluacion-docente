<?php

declare(strict_types=1);

// --- Validación genérica (login local, mientras no se retoma AD) ---

function validarUsuarioGenerico(string $usuario): bool
{
    $usuario = trim($usuario);
    $longitud = strlen($usuario);

    return $longitud >= 3 && $longitud <= 32 && preg_match('/^[A-Za-z0-9_.]+$/', $usuario) === 1;
}

function validarPasswordGenerica(string $password): bool
{
    $longitud = strlen($password);

    return $longitud >= 8 && $longitud <= 64;
}

// --- Validación estricta de cuentas INET (retomar cuando vuelva el login con AD) ---

function validarMatricula(string $matricula): bool
{
    return (bool) preg_match('/^a\d{8}$/', strtolower(trim($matricula)));
}

function validarPasswordComplejidad(string $password): bool
{
    if (strlen($password) < 8) {
        return false;
    }

    $reglas = [
        '/[A-Z]/',         // mayúsculas
        '/[a-z]/',         // minúsculas
        '/[0-9]/',         // números
        '/[^A-Za-z0-9@]/', // símbolos; "@" queda excluido a propósito
    ];

    $cumplidas = 0;
    foreach ($reglas as $regla) {
        if (preg_match($regla, $password) === 1) {
            $cumplidas++;
        }
    }

    return $cumplidas >= 3;
}
