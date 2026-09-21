<?php

declare(strict_types=1);

/**
 * Autocomprobación del CRUD y del control de acceso por módulos.
 * No necesita base de datos ni framework.
 *
 *   php tests/test_crud.php
 */

require_once __DIR__ . '/../src/crud.php';
require_once __DIR__ . '/../src/helpers/modulos.php';

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

// Solo pasan las columnas declaradas: 'es_admin' se descarta
$datos = saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'nombre'        => '  Ana  ',
    'correo'        => 'ana@test.mx',
    'password_hash' => 'secreta123',
    'es_admin'      => '1',
    'id'            => '999',
], true);

assert(array_keys($datos) === ['usuario', 'nombre', 'correo', 'password_hash']);
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
], false);

assert(!array_key_exists('password_hash', $edicion));

// Al crear, la contraseña sí es obligatoria
lanza('contraseña vacía al crear debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'nombre'        => 'Ana',
    'correo'        => 'ana@test.mx',
    'password_hash' => '',
], true));

// Valor fuera de las opciones de un select
$cfgModulos = tablaConfig('modulos');
lanza('url con formato inválido debe rechazarse', static fn () => saneaEntrada($cfgModulos, [
    'nombre'    => 'Reportes',
    'url'       => '../../etc/passwd',
    'categoria' => 'Reportes',
], true));

// Escape de HTML
assert(e('<script>') === '&lt;script&gt;');

// --- Búsqueda por columna ---

// Solo pasan columnas que la tabla lista. 'password_hash' no está en 'listar',
// así que no se puede filtrar por él aunque venga en la URL.
$filtros = filtrosDesde($cfg, [
    'nombre'        => 'ana',
    'correo'        => 'ana@test.mx',
    'password_hash' => '$2y$',
    'inventada'     => 'x',
    'nombre` OR 1=1 --' => 'x',
]);

assert($filtros === ['nombre' => 'ana', 'correo' => 'ana@test.mx']);

// Los valores se recortan y los vacíos no filtran nada
assert(filtrosDesde($cfg, ['nombre' => '  ana  ']) === ['nombre' => 'ana']);
assert(filtrosDesde($cfg, ['nombre' => '   ']) === []);
assert(filtrosDesde($cfg, []) === []);

// Sin filtros no hay WHERE
$vacio = clausulaWhere([]);
assert($vacio['sql'] === '');
assert($vacio['valores'] === []);

// Un marcador por filtro, unidos con AND, y ningún valor interpolado en el SQL
$where = clausulaWhere(['nombre' => 'ana', 'correo' => 'ana@test.mx']);
assert(substr_count($where['sql'], '?') === 2);
assert(str_contains($where['sql'], ' AND '));
assert(str_contains($where['sql'], '`nombre` LIKE ?'));
assert(!str_contains($where['sql'], 'ana'));
assert($where['valores'] === ['%ana%', '%ana@test.mx%']);

// Los comodines de LIKE se escapan: buscar "100%" no debe volverse un comodín
$comodines = clausulaWhere(['nombre' => '100%_a']);
assert($comodines['valores'] === ['%100\\%\\_a%']);
assert(str_contains($comodines['sql'], 'ESCAPE'));

// --- Columnas del panel de detalle ---

// La contraseña de usuarios se excluye sola por su 'hash' => true: es lo único
// que separa un hash bcrypt del HTML que ve cualquiera con acceso al módulo.
assert(columnasOcultas($cfg) === ['password_hash']);

// Una tabla sin campos con hash ni 'ocultar' no esconde nada
assert(columnasOcultas(tablaConfig('grupos')) === []);
assert(columnasOcultas(tablaConfig('modulos')) === []);

// 'ocultar' cubre columnas sensibles que no son contraseñas, y una columna que
// caiga en las dos listas no se repite
assert(columnasOcultas([
    'ocultar' => ['token', 'curp'],
    'campos'  => ['clave' => ['hash' => true]],
]) === ['token', 'curp', 'clave']);

assert(columnasOcultas([
    'ocultar' => ['clave'],
    'campos'  => ['clave' => ['hash' => true]],
]) === ['clave']);

// Sin ninguna de las dos claves no truena
assert(columnasOcultas([]) === []);

// --- Paginación ---

// La ventana se centra en la página actual y nunca se sale del rango.
assert(ventanaPaginas(1, 1) === [1]);
assert(ventanaPaginas(1, 10) === [1, 2, 3]);
assert(ventanaPaginas(2, 10) === [1, 2, 3]);
assert(ventanaPaginas(5, 10) === [4, 5, 6]);
assert(ventanaPaginas(10, 10) === [8, 9, 10]);

// Con menos páginas que el ancho de la ventana, se muestran solo las que hay
assert(ventanaPaginas(1, 2) === [1, 2]);
assert(ventanaPaginas(2, 2) === [1, 2]);

// La página actual siempre aparece en la ventana
foreach ([1, 3, 7, 12] as $paginas) {
    for ($p = 1; $p <= $paginas; $p++) {
        assert(in_array($p, ventanaPaginas($p, $paginas), true), "pagina {$p} de {$paginas} fuera de la ventana");
    }
}

// --- Módulos: whitelist de código ---

// Solo se puede abrir lo declarado en modules.php, aunque la base de datos
// tenga otras urls registradas.
assert(moduloConfig('usuario/admin')['crud'] === 'usuarios');
lanza('módulo no declarado debe rechazarse', static fn () => moduloConfig('reporte/general'));
lanza('ruta relativa no debe resolverse a un módulo', static fn () => moduloConfig('../../etc/passwd'));

// Cada módulo declara código: o una tabla del CRUD, o un archivo de src/modules/
foreach (modulos() as $url => $mod) {
    assert(validarUrlModulo($url), "url de módulo con formato inválido: {$url}");
    assert(isset($mod['crud']) !== isset($mod['archivo']), "módulo sin código o con dos: {$url}");

    if (isset($mod['crud'])) {
        tablaConfig($mod['crud']); // lanza si la tabla no está declarada
    } else {
        assert($mod['archivo'] === basename($mod['archivo']), "archivo con ruta: {$url}");
        assert(is_file(__DIR__ . '/../src/modules/' . $mod['archivo']), "archivo inexistente: {$url}");
        assert(isset($mod['volver']), "módulo auxiliar sin 'volver': {$url}");
    }
}

// El formato de url no acepta rutas relativas ni caracteres raros
assert(validarUrlModulo('grupo/permisos'));
assert(validarUrlModulo('reporte'));
assert(!validarUrlModulo('../etc/passwd'));
assert(!validarUrlModulo('grupo/'));
assert(!validarUrlModulo(''));

// --- Relaciones N:N ---

// Los identificadores del pivote pasan por la misma barrera que el resto
$pivote = ['tabla' => 'grupo_modulo', 'propia' => 'grupo_id', 'ajena' => 'modulo_id'];
assert(pivoteIdent($pivote) === ['`grupo_modulo`', '`grupo_id`', '`modulo_id`']);
lanza('pivote con inyección debe rechazarse', static fn () => pivoteIdent(
    ['tabla' => 'x` --', 'propia' => 'a', 'ajena' => 'b']
));

// Lo que llega del formulario son strings: se vuelven enteros, sin repetidos,
// sin vacíos y sin nada que no sea un id.
assert(pivoteIds(['3', '1', '3', '', '0', 'x']) === [3, 1]);
assert(pivoteIds([]) === []);

// --- Acciones de módulo (menú lateral) ---

// Las que apuntan al mismo módulo con 'params' no consultan permisos, así que
// se pueden comprobar sin base de datos ni sesión.
$acciones = accionesDeModulo('grupo/admin');
assert($acciones[0] === ['etiqueta' => 'Exportar CSV', 'href' => '?m=grupo/admin&accion=exportar']);
assert($acciones[1]['href'] === '?m=grupo/admin&accion=importar');

// Solo salen las de 'params': las que apuntan a otro módulo pasan por puede(),
// y sin sesión no hay permiso que valga. Que se pinten o no es cosa de quien
// mire el menú, no de la declaración.
assert(count($acciones) === 2);

// Las de otro módulo entran sin ?id= a propósito: la pantalla pregunta primero
// sobre qué registro se trabaja. El atajo con el id ya puesto vive en el panel
// de detalle.
$aOtroModulo = array_column(moduloConfig('grupo/admin')['acciones'], 'modulo');
assert($aOtroModulo === ['grupo/permisos', 'grupo/usuarios']);

// Una acción que apunta a otro módulo tiene que existir en la whitelist, o el
// enlace del menú llevaría a un 404
foreach (modulos() as $url => $mod) {
    foreach ($mod['acciones'] ?? [] as $accion) {
        assert(isset($accion['etiqueta']), "acción sin etiqueta en {$url}");
        assert(isset($accion['params']) !== isset($accion['modulo']),
            "acción sin destino o con dos en {$url}: {$accion['etiqueta']}");

        if (isset($accion['modulo'])) {
            moduloConfig($accion['modulo']); // lanza si no está declarado
        }
    }
}

// Un módulo sin acciones declaradas no ensucia el menú con un desplegable vacío
assert(accionesDeModulo('grupo/permisos') === []);
assert(accionesDeModulo('modulo/inexistente') === []);

// Importar solo donde se declaró: usuario/admin exporta pero no importa
$declaradas = array_column(array_column(moduloConfig('usuario/admin')['acciones'], 'params'), 'accion');
assert(in_array('exportar', $declaradas, true));
assert(!in_array('importar', $declaradas, true));

// --- Lectura de un CSV ---

$ruta = tempnam(sys_get_temp_dir(), 'csv');

// Con BOM (como lo guarda Excel), una columna no declarada y una línea en blanco
file_put_contents($ruta, "\xEF\xBB\xBFnombre,es_admin,descripcion\n"
    . "Profesores,1,Imparten clase\n"
    . "\n"
    . "Alumnos,0,Contestan la evaluacion\n");

$filas = leerCsv($ruta, ['nombre', 'descripcion']);

assert(count($filas) === 2, 'la línea en blanco no debe contar como fila');
assert($filas[0]['datos'] === ['nombre' => 'Profesores', 'descripcion' => 'Imparten clase'],
    'el BOM debe quitarse y es_admin debe ignorarse');
// La línea se reporta tal como la ve quien abre el archivo, contando el encabezado
assert($filas[0]['linea'] === 2);
assert($filas[1]['linea'] === 4);

// Un encabezado sin ninguna columna conocida no se importa "a medias": se rechaza
file_put_contents($ruta, "columna_rara,otra\nx,y\n");
lanza('encabezado sin columnas conocidas debe rechazarse',
    static fn () => leerCsv($ruta, ['nombre', 'descripcion']));

file_put_contents($ruta, '');
lanza('archivo vacío debe rechazarse', static fn () => leerCsv($ruta, ['nombre']));

unlink($ruta);

echo "OK: todas las comprobaciones pasaron.\n";
