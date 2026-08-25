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

// --- Búsqueda por columna ---

// Solo pasan columnas que la tabla lista. 'password_hash' no está en 'listar',
// así que no se puede filtrar por él aunque venga en la URL.
$filtros = filtrosDesde($cfg, [
    'nombre'        => 'ana',
    'rol'           => 'docente',
    'password_hash' => '$2y$',
    'inventada'     => 'x',
    'nombre` OR 1=1 --' => 'x',
]);

assert($filtros === ['nombre' => 'ana', 'rol' => 'docente']);

// Los valores se recortan y los vacíos no filtran nada
assert(filtrosDesde($cfg, ['nombre' => '  ana  ']) === ['nombre' => 'ana']);
assert(filtrosDesde($cfg, ['nombre' => '   ']) === []);
assert(filtrosDesde($cfg, []) === []);

// Sin filtros no hay WHERE
$vacio = clausulaWhere([]);
assert($vacio['sql'] === '');
assert($vacio['valores'] === []);

// Un marcador por filtro, unidos con AND, y ningún valor interpolado en el SQL
$where = clausulaWhere(['nombre' => 'ana', 'rol' => 'docente']);
assert(substr_count($where['sql'], '?') === 2);
assert(str_contains($where['sql'], ' AND '));
assert(str_contains($where['sql'], '`nombre` LIKE ?'));
assert(!str_contains($where['sql'], 'ana'));
assert($where['valores'] === ['%ana%', '%docente%']);

// Los comodines de LIKE se escapan: buscar "100%" no debe volverse un comodín
$comodines = clausulaWhere(['nombre' => '100%_a']);
assert($comodines['valores'] === ['%100\\%\\_a%']);
assert(str_contains($comodines['sql'], 'ESCAPE'));

echo "OK: todas las comprobaciones pasaron.\n";
