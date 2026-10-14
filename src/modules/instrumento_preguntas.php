<?php

declare(strict_types=1);

/**
 * Las preguntas de un instrumento, en el orden en que se le presentan al
 * alumno. Se reordenan arrastrando (ordenar.js) o con los botones ↑ ↓; en los
 * dos casos se manda la lista completa y reordenar() renumera 1..n.
 *
 * Recibe del front controller:
 *   $m    string  Url del módulo
 *   $mod  array   Su configuración: titulo, volver
 */

/** @var string $m */
/** @var array $mod */

$id = (int) ($_GET['id'] ?? 0);

$pdo = getDbConnection();

// Desde el menú lateral no hay instrumento elegido: se pregunta primero.
if ($id === 0) {
    $titulo = $mod['titulo'];

    componente('selector', [
        'titulo'   => $mod['titulo'],
        'm'        => $m,
        'opciones' => opcionesDe($pdo, ['tabla' => 'instrumentos', 'muestra' => 'nombre']),
        'muestra'  => 'nombre',
        'volver'   => $mod['volver'],
    ]);

    return;
}

$instrumento = obtener($pdo, 'instrumentos', $id);

if ($instrumento === null) {
    http_response_code(404);
    exit('Instrumento no encontrado.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificaCsrf();

    $ids  = pivoteIds((array) ($_POST['orden'] ?? []));
    $foco = (int) ($_POST['foco'] ?? 0);

    // Botones ↑ ↓: "id:-1" sube, "id:1" baja.
    if (isset($_POST['mover'])) {
        [$foco, $paso] = array_map('intval', explode(':', (string) $_POST['mover'], 2) + [1 => 0]);
        $ids = moverEnLista($ids, $foco, $paso < 0 ? -1 : 1);
    }

    try {
        reordenar($pdo, 'preguntas', 'instrumento_id', $id, $ids);

        // El ancla regresa la vista a la pregunta que se movió.
        header('Location: ' . urlModulo($m, ['id' => $id]) . ($foco > 0 ? "#pregunta-{$foco}" : ''));
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.pregunta, t.nombre AS tipo, d.nombre AS dimension
     FROM preguntas p
     JOIN tipo_preguntas t ON t.id = p.tipo_id
     JOIN dimensiones d ON d.id = p.dimension_id
     WHERE p.instrumento_id = ?
     ORDER BY p.orden, p.id'
);
$stmt->execute([$id]);

$titulo = $mod['titulo'];
$js     = ['ordenar.js'];

if ($error !== null) {
    echo '<p class="error">', e($error), '</p>';
}

componente('preguntas_instrumento', [
    'm'           => $m,
    'instrumento' => $instrumento,
    'preguntas'   => $stmt->fetchAll(),
    'editable'    => puede('pregunta/admin'),
    'volver'      => $mod['volver'],
]);
