<?php

declare(strict_types=1);

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
