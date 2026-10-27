<?php

declare(strict_types=1);

/**
 * Carga masiva desde un .txt separado por pipes o un .csv. Pareja de la acción
 * 'exportar': el archivo que baja el listado sirve de plantilla para el que se sube.
 *
 * Props:
 *   m           string    Url del módulo
 *   cfg         array     Configuración de la tabla
 *   columnas    string[]  Columnas que el archivo puede traer
 *   errores     string[]  Motivos por los que falló la importación anterior
 *   urlListado  string    A dónde va "Cancelar"
 *
 * Nunca se reimprime el contenido del archivo subido: solo el número de fila y
 * el mensaje de validación.
 */

$obligatorias = [];
$importar     = $cfg['importar'] ?? [];

foreach ($cfg['campos'] as $col => $campo) {
    if (!empty($campo['requerido'])) {
        $obligatorias[] = $col;
    }
}
?>

<h1>Importar — <?= e($cfg['etiqueta']) ?></h1>

<?php if ($errores !== []): ?>
    <div class="error">
        <p>No se importó ningún registro. Corrige el archivo y vuelve a subirlo:</p>
        <ul>
            <?php foreach ($errores as $mensaje): ?>
                <li><?= e($mensaje) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<p class="ayuda">
    La primera línea del archivo son los nombres de las columnas. Se aceptan
    <strong><?= e(implode(', ', $columnas)) ?></strong>; cualquier otra se ignora.
    <?php if ($obligatorias !== []): ?>
        No pueden ir vacías: <strong><?= e(implode(', ', $obligatorias)) ?></strong>.
    <?php endif; ?>
    El <code>id</code> lo pone la base de datos, no el archivo.
</p>

<?php if (!empty($importar['encabezados'])): ?>
    <p class="ayuda">
        También se aceptan los encabezados del sistema escolar:
        <strong><?= e(implode('|', array_keys($importar['encabezados']))) ?></strong>.
    </p>
<?php endif; ?>

<?php if (isset($importar['llave'])): ?>
    <p class="ayuda">
        Una fila cuya <strong><?= e($importar['llave']) ?></strong> ya existe actualiza
        ese registro; las demás se dan de alta.
    </p>
<?php endif; ?>

<?php if (isset($cfg['usuario_propio'])): ?>
    <p class="ayuda">
        La cuenta de cada alta se crea sola: el usuario es lo que va antes de la @
        del correo y la contraseña es la genérica del sistema.
    </p>
<?php endif; ?>

<p class="ayuda">
    Se importa todo o no se importa nada: si una sola fila falla, se deshace la
    carga completa y aquí abajo sale qué fila fue.
</p>

<form method="post"
      action="<?= e(urlModulo($m, ['accion' => 'importar'])) ?>"
      enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <label for="archivo">Archivo (.txt separado por | o .csv separado por comas)</label>
    <input type="file" id="archivo" name="archivo" accept=".txt,.csv,text/plain,text/csv" required>

    <div class="acciones-form">
        <button type="submit" class="boton">Importar</button>
        <a href="<?= e($urlListado) ?>">Cancelar</a>
    </div>
</form>
