<?php

declare(strict_types=1);

/**
 * Alumnos: cada uno es una fila de `alumnos` más el usuario con el que entra.
 *
 * Se ve igual que un CRUD genérico (mismos componentes), pero no lo es: dar de
 * alta un alumno crea su usuario (usuario = "a" + matrícula, correo
 * institucional, grupo "Alumno") y las dos escrituras van en una transacción.
 *
 * Recibe del front controller:
 *   $m    string  Url del módulo
 */

/** @var string $m */

require_once __DIR__ . '/../helpers/ldap_auth.php';

$accion = (string) ($_GET['accion'] ?? 'listar');
$id     = (int) ($_GET['id'] ?? 0);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$pdo      = getDbConnection();
$error    = null;
$registro = [];

// No está en tables.php (no es una tabla del CRUD genérico): esta config solo
// alimenta a los componentes y a saneaEntrada().
$cfg = [
    'etiqueta' => 'Alumnos',
    'listar'   => ['id', 'matricula', 'usuario', 'estado'],
    'campos'   => [
        'matricula' => [
            'etiqueta'  => 'Matrícula',
            'tipo'      => 'text',
            'requerido' => true,
            'patron'    => 'validarMatriculaAlumno',
        ],
        'password_hash' => [
            'etiqueta'  => 'Contraseña',
            'tipo'      => 'password',
            'requerido' => true,
            'hash'      => true,
            'patron'    => 'validarPasswordGenerica',
        ],
        'estado' => [
            'etiqueta'  => 'Estado',
            'tipo'      => 'select',
            'requerido' => true,
            'opciones'  => ['1', '0'],
            'etiquetas' => ['1' => 'Activo', '0' => 'Inactivo'],
            'defecto'   => '1',
        ],
    ],
];

// Lo que se lista y busca. Es una subconsulta para que clausulaWhere() filtre
// por estas columnas sin ambigüedad (`id` existe en las dos tablas), y el
// estado ya sale como texto para que se pueda buscar "activo".
$fuente = "(SELECT a.id, a.matricula, u.usuario,
                   IF(u.estado = 1, 'Activo', 'Inactivo') AS estado
            FROM alumnos a JOIN usuarios u ON u.id = a.usuario_id) AS lista";

$filtros    = filtrosDesde($cfg, (array) ($_GET['f'] ?? []));
$urlListado = urlModulo($m, ['f' => $filtros, 'pagina' => $pagina]);

/* Guarda un alumno y su usuario (alta si $id es 0). La transacción la abre
   quien llama: el formulario guarda uno, la importación guarda muchos. */
$guardar = static function (PDO $pdo, array $cfg, array $datos, int $id, int $usuarioId): void {
    verificaUnicidad($pdo, 'alumnos', $cfg, ['matricula' => $datos['matricula']], $id);

    $usuario = [
        'usuario' => 'a' . $datos['matricula'],
        'correo'  => construirCorreoInstitucional('a' . $datos['matricula']),
        'estado'  => $datos['estado'],
    ];

    // Al editar sin contraseña, saneaEntrada() no la devuelve: no se toca.
    if (isset($datos['password_hash'])) {
        $usuario['password_hash'] = $datos['password_hash'];
    }

    if ($id === 0) {
        crear($pdo, 'usuarios', $usuario + ['tipo' => 2]);
        $usuarioId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO alumnos (matricula, usuario_id, creado_por, editado_por) VALUES (?, ?, ?, ?)')
            ->execute([$datos['matricula'], $usuarioId, usuarioActual(), usuarioActual()]);

        // Sin el grupo "Alumno" en la base, la cuenta queda sin grupo.
        $pdo->prepare("INSERT INTO usuario_grupo (usuario_id, grupo_id)
                       SELECT ?, id FROM grupos WHERE nombre = 'Alumno'")
            ->execute([$usuarioId]);
    } else {
        actualizar($pdo, 'usuarios', $usuarioId, $usuario);

        $pdo->prepare('UPDATE alumnos SET matricula = ?, editado_por = ? WHERE id = ?')
            ->execute([$datos['matricula'], usuarioActual(), $id]);
    }
};

/**
 * Alta o actualización en bloque desde un .txt (pipes) o .csv. La llave es la
 * matrícula: una que ya existe se actualiza. Todo o nada, como importarCsv().
 * Las altas llevan la contraseña genérica (PASSWORD_GENERICA), nunca del archivo.
 *
 * @return string[] Errores; vacío si se importó todo
 */
$importar = static function (PDO $pdo, array $cfg, array $archivo) use ($guardar): array {
    $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return ['El archivo es demasiado grande.'];
    }

    if ($error !== UPLOAD_ERR_OK) {
        return ['No se recibió ningún archivo. Vuelve a intentarlo.'];
    }

    $ruta = (string) ($archivo['tmp_name'] ?? '');

    if (!is_uploaded_file($ruta)) {
        return ['El archivo no se subió correctamente.'];
    }

    if ((int) ($archivo['size'] ?? 0) > MAX_BYTES_CSV) {
        return ['El archivo pasa de ' . (int) (MAX_BYTES_CSV / 1024 / 1024) . ' MB. Divídelo en varias partes.'];
    }

    $separador = strtolower(pathinfo((string) ($archivo['name'] ?? ''), PATHINFO_EXTENSION)) === 'txt' ? '|' : ',';

    try {
        $filas = leerCsv($ruta, ['matricula', 'estado'], $separador);
    } catch (InvalidArgumentException $e) {
        return [$e->getMessage()];
    }

    if ($filas === []) {
        return ['El archivo no trae ninguna fila con datos.'];
    }

    $buscar = $pdo->prepare('SELECT a.id, a.usuario_id, u.estado FROM alumnos a
                             JOIN usuarios u ON u.id = a.usuario_id WHERE a.matricula = ?');

    $pdo->beginTransaction();

    try {
        foreach ($filas as $fila) {
            $entrada = $fila['datos'];

            $buscar->execute([trim($entrada['matricula'] ?? '')]);
            $actual = $buscar->fetch();

            $id        = (int) ($actual['id'] ?? 0);
            $usuarioId = (int) ($actual['usuario_id'] ?? 0);

            // Sin estado en el archivo: activo en un alta, el que tenía al actualizar.
            if (trim($entrada['estado'] ?? '') === '') {
                $entrada['estado'] = (string) ($actual['estado'] ?? '1');
            }

            if ($id === 0) {
                $generica = (string) getenv('PASSWORD_GENERICA');

                if ($generica === '') {
                    throw new InvalidArgumentException('Falta configurar PASSWORD_GENERICA en el servidor.');
                }

                $entrada['password_hash'] = $generica;
            }

            $guardar($pdo, $cfg, saneaEntrada($cfg, $entrada, $id === 0), $id, $usuarioId);
        }

        $pdo->commit();

        return [];
    } catch (InvalidArgumentException $e) {
        $pdo->rollBack();

        return ["Línea {$fila['linea']}: {$e->getMessage()}"];
    } catch (PDOException) {
        $pdo->rollBack();

        return ["Línea {$fila['linea']}: la base de datos rechazó el registro. Revisa que no haya valores duplicados."];
    }
};

// Exportar e importar solo si modules.php las declaró (igual que tabla_crud.php).
$declaradas = array_column(array_column($mod['acciones'] ?? [], 'params'), 'accion');

if (in_array($accion, ['exportar', 'importar'], true)
    && !in_array($accion, $declaradas, true)) {
    http_response_code(404);
    exit('Acción no disponible para este módulo.');
}

if ($accion === 'exportar') {
    // Un archivo no lleva layout: se tira el buffer del front controller.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Respeta la búsqueda del listado. Sin hash ni auditoría: las contraseñas
    // no salen de la base. El estado va como 1/0 para poder reimportarlo.
    $where = clausulaWhere($filtros);

    $stmt = $pdo->prepare(
        'SELECT a.matricula, u.usuario, u.correo, u.estado
         FROM alumnos a JOIN usuarios u ON u.id = a.usuario_id
         WHERE a.id IN (SELECT id FROM ' . $fuente . $where['sql'] . ')
         ORDER BY a.id DESC'
    );
    $stmt->execute($where['valores']);

    $txt = ($_GET['formato'] ?? '') === 'txt';

    [$separador, $extension, $tipo] = $txt
        ? ['|', 'txt', 'text/plain']
        : [',', 'csv', 'text/csv'];

    header('Content-Type: ' . $tipo . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="alumnos-' . date('Y-m-d') . '.' . $extension . '"');

    $salida = fopen('php://output', 'w');

    // BOM UTF-8 para que Excel no rompa los acentos; al importar se quita solo.
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, ['matricula', 'usuario', 'correo', 'estado'], $separador, '"', '');

    while (($fila = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
        fputcsv($salida, $fila, $separador, '"', '');
    }

    fclose($salida);
    exit;
}

$erroresImport = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'importar') {
    verificaCsrf();

    $erroresImport = $importar($pdo, $cfg, $_FILES['archivo'] ?? []);

    if ($erroresImport === []) {
        header('Location: ' . $urlListado);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT usuario_id FROM alumnos WHERE id = ?');
        $stmt->execute([$id]);
        $usuarioId = (int) $stmt->fetchColumn();

        if ($id !== 0 && $usuarioId === 0) {
            throw new InvalidArgumentException('El alumno ya no existe.');
        }

        if ($accion === 'eliminar') {
            /* Se borra el usuario y el alumno cae con él (FK ON DELETE CASCADE).
               Para conservar el usuario y borrar solo al alumno, cambiar esta
               línea por: DELETE FROM alumnos WHERE id = ? con [$id]. */
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$usuarioId]);
        } else {
            $guardar($pdo, $cfg, saneaEntrada($cfg, $_POST, $id === 0), $id, $usuarioId);
        }

        $pdo->commit();

        header('Location: ' . $urlListado);
        exit;
    } catch (InvalidArgumentException $e) {
        $pdo->rollBack();
        $error    = $e->getMessage();
        $registro = $_POST;
    } catch (PDOException) {
        $pdo->rollBack();
        $error = $accion === 'eliminar'
            ? 'No se pudo eliminar: hay registros que dependen de este.'
            : 'No se pudo guardar el registro. Revisa que no haya valores duplicados.';
        $registro = $_POST;
    }
}

if ($accion === 'editar' && $id > 0 && $registro === []) {
    $stmt = $pdo->prepare('SELECT a.matricula, u.estado FROM alumnos a
                           JOIN usuarios u ON u.id = a.usuario_id WHERE a.id = ?');
    $stmt->execute([$id]);
    $registro = $stmt->fetch() ?: [];

    if ($registro === []) {
        http_response_code(404);
        exit('Registro no encontrado.');
    }
}

if ($accion === 'importar') {
    $titulo = 'Importar ' . $cfg['etiqueta'];

    // El importador lista como obligatorias las columnas 'requeridas' del cfg:
    // la contraseña no viene en el archivo y el estado es opcional.
    $cfgImportar = [
        'etiqueta' => $cfg['etiqueta'],
        'campos'   => array_diff_key($cfg['campos'], ['password_hash' => true]),
        'importar' => ['llave' => 'matricula'],
    ];
    $cfgImportar['campos']['estado']['requerido'] = false;

    componente('importador', [
        'm'          => $m,
        'cfg'        => $cfgImportar,
        'columnas'   => array_keys($cfgImportar['campos']),
        'errores'    => $erroresImport,
        'urlListado' => $urlListado,
    ]);

    return;
}

$titulo = $cfg['etiqueta'];

if ($error !== null) {
    echo '<p class="error">', e($error), '</p>';
}

// Un borrado que falló deja $error y hay que seguir mostrando el listado.
if ($accion !== 'listar' && $accion !== 'eliminar') {
    componente('formulario', [
        'm'          => $m,
        'cfg'        => $cfg,
        'registro'   => $registro,
        'id'         => $id,
        'filtros'    => $filtros,
        'urlListado' => $urlListado,
    ]);

    return;
}

// Misma paginación que listar() en crud.php, sobre la subconsulta.
$where = clausulaWhere($filtros);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $fuente . $where['sql']);
$stmt->execute($where['valores']);

$total   = (int) $stmt->fetchColumn();
$paginas = max(1, (int) ceil($total / FILAS_POR_PAGINA));
$pagina  = max(1, min($pagina, $paginas));

$stmt = $pdo->prepare('SELECT * FROM ' . $fuente . $where['sql'] . ' ORDER BY id DESC LIMIT ? OFFSET ?');

$posicion = 1;

foreach ($where['valores'] as $valor) {
    $stmt->bindValue($posicion++, $valor);
}

$stmt->bindValue($posicion++, FILAS_POR_PAGINA, PDO::PARAM_INT);
$stmt->bindValue($posicion, ($pagina - 1) * FILAS_POR_PAGINA, PDO::PARAM_INT);
$stmt->execute();

componente('tabla', [
    'm'        => $m,
    'cfg'      => $cfg,
    'listado'  => [
        'filas'     => $stmt->fetchAll(),
        'pagina'    => $pagina,
        'paginas'   => $paginas,
        'total'     => $total,
        'porPagina' => FILAS_POR_PAGINA,
    ],
    'filtros'  => $filtros,
    'columnas' => ['id', 'matricula', 'usuario', 'estado'],
]);
