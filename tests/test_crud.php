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
    'usuario'       => '  ana.lopez  ',
    'correo'        => 'ana@test.mx',
    'password_hash' => 'secreta123',
    'estado'        => '1',
    'es_admin'      => '1',
    'id'            => '999',
], true);

assert(array_keys($datos) === ['usuario', 'correo', 'password_hash', 'estado']);
assert($datos['usuario'] === 'ana.lopez');
assert($datos['password_hash'] !== 'secreta123');
assert(password_verify('secreta123', $datos['password_hash']));

// Campo requerido vacío
lanza('correo vacío debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'correo'        => '',
    'password_hash' => 'secreta123',
    'estado'        => '1',
], true));

// El estado solo es 1 (activo) o 0 (inactivo)
lanza('estado fuera de opciones debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'correo'        => 'ana@test.mx',
    'password_hash' => 'secreta123',
    'estado'        => '2',
], true));

// El CRUD aplica las mismas reglas de validation.php que usa el login
lanza('usuario con espacios debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana lopez',
    'correo'        => 'ana@test.mx',
    'estado'        => '1',
    'password_hash' => 'secreta123',
], true));

lanza('contraseña corta debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'correo'        => 'ana@test.mx',
    'estado'        => '1',
    'password_hash' => 'corta',
], true));

// --- Tipo de pregunta: tipo fijo y respuestas como pares valor:respuesta ---

$tipos = tablaConfig('tipo_preguntas');

// Solo 1, 2 o 3
foreach (['0', '4', '-1', 'abc'] as $malo) {
    lanza("tipo {$malo} debe rechazarse", static fn () => saneaEntrada($tipos, [
        'nombre' => 'Likert',
        'tipo'   => $malo,
    ], true));
}

// Instrucción y abierta no guardan respuestas aunque el formulario mande filas
$abierta = saneaEntrada($tipos, [
    'nombre'    => 'Abierta',
    'tipo'      => '2',
    'respuesta' => ['valor' => ['1'], 'texto' => ['Sí']],
], true);
assert($abierta['respuesta'] === null);

// Opción múltiple: las filas del formulario se unen, las vacías se ignoran
$multiple = saneaEntrada($tipos, [
    'nombre'    => 'Dicotómica',
    'tipo'      => '3',
    'respuesta' => ['valor' => ['1', '2', ''], 'texto' => ['Sí', ' No ', '']],
], true);
assert($multiple['respuesta'] === '1:Sí,2:No');

// El texto guardado (o el de un CSV) se lee de vuelta en filas, y pasa igual
assert(filasDePares('1:Sí,2:No') === [['1', 'Sí'], ['2', 'No']]);
assert(filasDePares('') === []);
assert(saneaEntrada($tipos, ['nombre' => 'X', 'tipo' => '3', 'respuesta' => '0:Nunca,1:A veces'], true)['respuesta']
    === '0:Nunca,1:A veces');

// Opción múltiple sin respuestas, o con filas mal formadas
$malas = [
    'sin filas'        => ['valor' => [''], 'texto' => ['']],
    'falta respuesta'  => ['valor' => ['1'], 'texto' => ['']],
    'falta valor'      => ['valor' => [''], 'texto' => ['Sí']],
    'valor no entero'  => ['valor' => ['1.5'], 'texto' => ['Sí']],
    'coma en el texto' => ['valor' => ['1'], 'texto' => ['Sí, siempre']],
    'valor repetido'   => ['valor' => ['1', '1'], 'texto' => ['Sí', 'No']],
];

foreach ($malas as $caso => $respuesta) {
    lanza("opción múltiple con {$caso} debe rechazarse", static fn () => saneaEntrada($tipos, [
        'nombre'    => 'Likert',
        'tipo'      => '3',
        'respuesta' => $respuesta,
    ], true));
}

// Al editar, contraseña vacía no toca la columna
$edicion = saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'correo'        => 'ana@test.mx',
    'estado'        => '1',
    'password_hash' => '',
], false);

assert(!array_key_exists('password_hash', $edicion));

// Al crear, la contraseña sí es obligatoria
lanza('contraseña vacía al crear debe rechazarse', static fn () => saneaEntrada($cfg, [
    'usuario'       => 'ana.lopez',
    'correo'        => 'ana@test.mx',
    'estado'        => '1',
    'password_hash' => '',
], true));

// Matrícula de alumno: exactamente 8 dígitos
assert(validarMatriculaAlumno('12345678'));
foreach (['1234567', '123456789', 'a1234567', '1234 678', ''] as $mala) {
    assert(!validarMatriculaAlumno($mala), $mala);
}

// Valor fuera de las opciones de un select
$cfgModulos = tablaConfig('modulos');
lanza('url con formato inválido debe rechazarse', static fn () => saneaEntrada($cfgModulos, [
    'nombre'    => 'Reportes',
    'url'       => '../../etc/passwd',
    'categoria' => 'Reportes',
], true));

// Escape de HTML
assert(e('<script>') === '&lt;script&gt;');

// --- Campos 'html' (editor con formato) ---

// El formato del editor pasa tal cual
$conFormato = '<p class="ql-align-center">Las <strong>fortalezas</strong> son:</p><ul><li>Sí, ñandú &amp; &lt;b&gt;</li></ul>';
assert(limpiaHtml($conFormato) === $conFormato);

// Lo que puede ejecutar código se va; el texto alrededor se queda
assert(limpiaHtml('<p onclick="x()" style="color:red" class="ql-align-center otra">a</p>')
    === '<p class="ql-align-center">a</p>');
assert(limpiaHtml('<p>a</p><script>alert(1)</script><img src=x onerror=alert(1)>') === '<p>a</p>');
assert(limpiaHtml('<svg><a href="https://x"><script>alert(1)</script></a></svg><p>b</p>') === '<p>b</p>');
assert(limpiaHtml('</body><script>alert(1)</script><p>c</p><!-- x -->') === '<p>c</p>');
assert(limpiaHtml('<div>d<span>e</span></div>') === 'de');

// Enlaces: solo http, https y mailto, y siempre en pestaña nueva sin opener
foreach (['javascript:alert(1)', ' JaVaScRiPt:alert(1)', "java\tscript:alert(1)", 'data:text/html,x', '/ruta'] as $href) {
    assert(!str_contains(limpiaHtml('<a href="' . e($href) . '">x</a>'), 'href'), $href);
}
assert(limpiaHtml('<a href="https://uady.mx">u</a>')
    === '<a href="https://uady.mx" target="_blank" rel="noopener noreferrer">u</a>');

// Un editor vacío cuenta como vacío: no llena un campo requerido
assert(limpiaHtml('<p></p>') === '' && limpiaHtml('<p><br></p>') === '' && limpiaHtml('') === '');
lanza('pregunta vacía debe rechazarse', static fn () => saneaEntrada(tablaConfig('preguntas'), [
    'instrumento_id' => '1', 'dimension_id' => '1', 'tipo_id' => '1', 'orden' => '1',
    'pregunta'       => '<p><br></p>',
], true));

// En el listado sale una línea de texto, sin pegar palabras de bloques distintos
assert(textoPlano('<p>Uno <strong>dos</strong>tres</p><ul><li>cuatro</li></ul><p>&amp; cinco</p>')
    === 'Uno dostres cuatro & cinco');

// --- Búsqueda por columna ---

// Solo pasan columnas que la tabla lista. 'password_hash' no está en 'listar',
// así que no se puede filtrar por él aunque venga en la URL.
$filtros = filtrosDesde($cfg, [
    'usuario'       => 'ana',
    'correo'        => 'ana@test.mx',
    'password_hash' => '$2y$',
    'inventada'     => 'x',
    'usuario` OR 1=1 --' => 'x',
]);

assert($filtros === ['usuario' => 'ana', 'correo' => 'ana@test.mx']);

// Los valores se recortan y los vacíos no filtran nada
assert(filtrosDesde($cfg, ['usuario' => '  ana  ']) === ['usuario' => 'ana']);
assert(filtrosDesde($cfg, ['usuario' => '   ']) === []);
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

// --- Orden de las preguntas de un instrumento ---

// ↑ ↓ intercambian con el vecino; en los extremos y con un id ajeno no pasa nada
assert(moverEnLista([1, 2, 3], 3, -1) === [1, 3, 2]);
assert(moverEnLista([1, 2, 3], 1, 1) === [2, 1, 3]);
assert(moverEnLista([1, 2, 3], 1, -1) === [1, 2, 3]);
assert(moverEnLista([1, 2, 3], 3, 1) === [1, 2, 3]);
assert(moverEnLista([1, 2, 3], 9, 1) === [1, 2, 3]);

// reordenar() contra una base de verdad: SQLite en memoria, sin tocar MySQL
$sqlite = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sqlite->exec('CREATE TABLE preguntas (id INTEGER PRIMARY KEY, instrumento_id INT, orden INT, editado_por TEXT)');
$sqlite->exec('INSERT INTO preguntas VALUES (1, 1, 1, NULL), (2, 1, 2, NULL), (3, 1, 3, NULL), (4, 1, 4, NULL),
                                            (5, 1, 5, NULL), (9, 2, 1, NULL)');
$ordenDe = static fn (int $instrumento): array => array_map('intval', $sqlite
    ->query("SELECT id FROM preguntas WHERE instrumento_id = {$instrumento} ORDER BY orden")
    ->fetchAll(PDO::FETCH_COLUMN));

// Mover la 5 al lugar 2: las que seguían se recorren solas y quedan 1..n sin huecos
reordenar($sqlite, 'preguntas', 'instrumento_id', 1, [1, 5, 2, 3, 4]);
assert($ordenDe(1) === [1, 5, 2, 3, 4]);
assert($sqlite->query('SELECT GROUP_CONCAT(orden) FROM (SELECT orden FROM preguntas WHERE instrumento_id = 1 ORDER BY orden)')
    ->fetchColumn() === '1,2,3,4,5');

// La lista debe traer exactamente las preguntas del instrumento: ni faltantes,
// ni de otro instrumento, ni de más. Si no, nada cambia.
foreach ([[1, 5, 2, 3], [1, 5, 2, 3, 4, 9], [1, 5, 2, 3, 4, 77]] as $mala) {
    lanza('lista de preguntas incompleta o ajena debe rechazarse',
        static fn () => reordenar($sqlite, 'preguntas', 'instrumento_id', 1, $mala));
}
assert($ordenDe(1) === [1, 5, 2, 3, 4] && $ordenDe(2) === [9]);

// Orden de una pregunta: en un alta no se pide (crear() la pone al final);
// al editar es obligatorio y nunca menor que 1
$preguntas = tablaConfig('preguntas');
$base      = ['instrumento_id' => '1', 'dimension_id' => '1', 'tipo_id' => '1', 'pregunta' => '<p>¿Ok?</p>'];

assert(saneaEntrada($preguntas, $base, true)['orden'] === null);
lanza('orden vacío al editar debe rechazarse', static fn () => saneaEntrada($preguntas, $base, false));

foreach (['0', '-3', '1.5'] as $malo) {
    lanza("orden {$malo} debe rechazarse", static fn () => saneaEntrada($preguntas, $base + ['orden' => $malo], false));
}
assert(saneaEntrada($preguntas, $base + ['orden' => '1'], false)['orden'] === '1');

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
assert($acciones[0] === ['etiqueta' => 'Exportar CSV', 'href' => '?m=grupo/admin&accion=exportar&formato=csv']);
assert($acciones[1]['href'] === '?m=grupo/admin&accion=exportar&formato=txt');
assert($acciones[2]['href'] === '?m=grupo/admin&accion=importar');

// Solo salen las de 'params': las que apuntan a otro módulo pasan por puede(),
// y sin sesión no hay permiso que valga. Que se pinten o no es cosa de quien
// mire el menú, no de la declaración.
assert(count($acciones) === 3);

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

// .txt con pipes y encabezados del sistema escolar; una coma dentro de un campo es texto
file_put_contents($ruta, "ClaveProfesor|NombreProfesor|Correo|Extra\n"
    . "P01|Ana, María|ana@uady.mx|x\n");

$filas = leerCsv($ruta, ['clave', 'nombres', 'correo'], '|', ['ClaveProfesor' => 'clave', 'NombreProfesor' => 'nombres', 'Correo' => 'correo']);
assert($filas[0]['datos'] === ['clave' => 'P01', 'nombres' => 'Ana, María', 'correo' => 'ana@uady.mx'],
    'los alias deben mapear a columnas y la coma no debe partir el campo');

// Un archivo guardado en Windows-1252 llega a UTF-8 con sus acentos
file_put_contents($ruta, "nombre\n" . mb_convert_encoding('Pérez Núñez', 'Windows-1252', 'UTF-8') . "\n");
assert(leerCsv($ruta, ['nombre'], '|')[0]['datos']['nombre'] === 'Pérez Núñez');

unlink($ruta);

// --- Profesores: el usuario se guarda aparte ---

$cfgProf = tablaConfig('profesores');
$datos   = saneaEntrada($cfgProf, [
    'clave'            => 'P1',
    'nombres'          => 'Ana',
    'apellido_paterno' => 'López',
    'apellido_materno' => 'Pérez',
    'grado'            => '',
    'correo'           => 'ana@x.mx',
    'usuario'          => 'ana.lopez',
    'password_hash'    => 'Secreta123!',
    'estado'           => '1',
], true);

$usuario = filaDeUsuario($cfgProf, $datos);

// Los campos de usuario salen del registro y no llegan al INSERT de profesores
assert(!isset($datos['usuario']) && !isset($datos['password_hash']) && !isset($datos['estado']));
assert($usuario['usuario'] === 'ana.lopez' && $usuario['estado'] === '1');
assert(password_verify('Secreta123!', $usuario['password_hash']));
// el correo del usuario se copia del registro
assert(!isset($usuario['nombre']) && $usuario['correo'] === 'ana@x.mx');

// Al editar, contraseña vacía = no cambiar: no viaja al UPDATE de usuarios
$datos   = saneaEntrada($cfgProf, ['password_hash' => ''] + $datos + ['usuario' => 'ana.lopez', 'estado' => '0'], false);
$usuario = filaDeUsuario($cfgProf, $datos);
assert(!array_key_exists('password_hash', $usuario) && $usuario['estado'] === '0');

lanza('estado fuera de 1/0 debe rechazarse', static fn () => saneaEntrada(
    $cfgProf,
    ['estado' => '2'] + $datos + ['usuario' => 'ana.lopez'],
    false
));

// Importar: el usuario sale del correo, sin importar el dominio
putenv('PASSWORD_GENERICA=Generica123!');
$fila = completaUsuario($cfgProf, ['clave' => 'P1', 'correo' => 'ana.lopez@profesor.mx'], true);
assert($fila['usuario'] === 'ana.lopez' && $fila['password_hash'] === 'Generica123!' && $fila['estado'] === '1');

// Al actualizar no se ponen contraseña ni estado: se quedan los que tiene
$fila = completaUsuario($cfgProf, ['correo' => 'ana@uady.mx'], false);
assert($fila === ['correo' => 'ana@uady.mx', 'usuario' => 'ana']);

putenv('PASSWORD_GENERICA');
lanza('sin PASSWORD_GENERICA un alta importada debe rechazarse',
    static fn () => completaUsuario($cfgProf, ['correo' => 'a@b.mx'], true));

echo "OK: todas las comprobaciones pasaron.\n";
