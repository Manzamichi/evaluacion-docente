<?php

declare(strict_types=1);

/**
 * Autocomprobación del CRUD. No necesita base de datos ni framework.
 *
 *   php tests/test_crud.php
 */

require_once __DIR__ . '/../src/crud.php';

function lanza(string $mensaje, callable $fn): void
{
    try {
        $fn();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException("Falló: {$mensaje}");
}

$cfg = tablaConfig('usuarios');
assert($cfg['etiqueta'] === 'Usuarios');

// La whitelist bloquea cualquier tabla no declarada en tables.php
lanza('tabla no declarada debe rechazarse', static fn () => tablaConfig('information_schema.tables'));

// Los identificadores no aceptan nada fuera de [a-zA-Z0-9_]
assert(ident('usuarios') === '`usuarios`');
lanza('identificador con inyección debe rechazarse', static fn () => ident('usuarios` WHERE 1=1 --'));
lanza('identificador con backtick debe rechazarse', static fn () => ident('a`b'));

// Solo pasan las columnas declaradas: 'rol_admin' se descarta
$datos = saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'nombre'        => '  Ana  ',
    'correo'        => 'ana@test.mx',
    'password_hash' => 'secreta123',
    'rol'           => 'docente',
    'rol_admin'     => '1',
    'id'            => '999',
], true);

assert(array_keys($datos) === ['usuario', 'nombre', 'correo', 'password_hash', 'rol']);
assert($datos['nombre'] === 'Ana');
assert($datos['password_hash'] !== 'secreta123');
assert(password_verify('secreta123', $datos['password_hash']));

// Campo requerido vacío
lanza('nombre vacío debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'nombre'        => '',
    'correo'        => 'ana@test.mx',
    'password_hash' => 'secreta123',
], true));

// Valor fuera de las opciones del ENUM
lanza('rol inválido debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'nombre'        => 'Ana',
    'correo'        => 'ana@test.mx',
    'password_hash' => 'secreta123',
    'rol'           => 'superadmin',
], true));

// El CRUD aplica las mismas reglas de validation.php que usa el login
lanza('usuario con espacios debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana lopez',
    'nombre'        => 'Ana',
    'correo'        => 'ana@test.mx',
    'password_hash' => 'secreta123',
], true));

lanza('contraseña corta debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'nombre'        => 'Ana',
    'correo'        => 'ana@test.mx',
    'password_hash' => 'corta',
], true));

// Al editar, contraseña vacía no toca la columna
$edicion = saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'nombre'        => 'Ana',
    'correo'        => 'ana@test.mx',
    'password_hash' => '',
    'rol'           => 'alumno',
], false);

assert(!array_key_exists('password_hash', $edicion));

// Al crear, la contraseña sí es obligatoria
lanza('contraseña vacía al crear debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'nombre'        => 'Ana',
    'correo'        => 'ana@test.mx',
    'password_hash' => '',
], true));

// Escape de HTML
assert(e('<script>') === '&lt;script&gt;');

echo "OK: todas las comprobaciones pasaron.\n";
