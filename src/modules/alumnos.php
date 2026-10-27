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
                   IF(u.estado = 1, 'Activo', 'Inactivo') AS estado, u.creado_en
            FROM alumnos a JOIN usuarios u ON u.id = a.usuario_id) AS lista";

$filtros    = filtrosDesde($cfg, (array) ($_GET['f'] ?? []));
$urlListado = urlModulo($m, ['f' => $filtros, 'pagina' => $pagina]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $datos = saneaEntrada($cfg, $_POST, $id === 0);

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
                crear($pdo, 'usuarios', $usuario);
                $usuarioId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO alumnos (matricula, usuario_id) VALUES (?, ?)')
                    ->execute([$datos['matricula'], $usuarioId]);

                // Sin el grupo "Alumno" en la base, la cuenta queda sin grupo.
                $pdo->prepare("INSERT INTO usuario_grupo (usuario_id, grupo_id)
                               SELECT ?, id FROM grupos WHERE nombre = 'Alumno'")
                    ->execute([$usuarioId]);
            } else {
                actualizar($pdo, 'usuarios', $usuarioId, $usuario);

                $pdo->prepare('UPDATE alumnos SET matricula = ? WHERE id = ?')
                    ->execute([$datos['matricula'], $id]);
            }
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
    'columnas' => ['id', 'matricula', 'usuario', 'estado', 'creado_en'],
]);
