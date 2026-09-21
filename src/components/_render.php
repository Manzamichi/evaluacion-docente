<?php

declare(strict_types=1);

/**
 * Pinta un componente de src/components/.
 *
 *   componente('tabla', ['cfg' => $cfg, 'listado' => $listado]);
 *
 * Los props llegan al componente como variables sueltas, como los @Input de un
 * componente de Angular. El componente no ve nada más: al hacer el require
 * dentro de una función, el scope global queda fuera, así que lo que no venga
 * en $__props no existe ahí dentro. Si a un componente le falta un dato, se le
 * pasa; nunca se apoya en una variable que "ya andaba por ahí".
 *
 * Un componente solo imprime. Si necesita consultar la base de datos, la
 * consulta va en el módulo que lo llama.
 */
/**
 * URL de un asset de public/ con ?v=<mtime>, para que el navegador vuelva a
 * pedirlo cuando el archivo cambia y no se quede con una versión vieja en caché.
 *
 * La carpeta que se sirve como raíz cambia según el entorno (el Apache del
 * contenedor publica public/ como /var/www/html; `php -S -t public` la deja
 * tal cual), así que el archivo se busca primero bajo DOCUMENT_ROOT y, si no
 * aparece, en la ruta del repo.
 *
 *   assetVer('assets/js/app.js')  =>  'assets/js/app.js?v=1712345678'
 */
function assetVer(string $ruta): string
{
    $ruta = ltrim($ruta, '/');

    foreach ([($_SERVER['DOCUMENT_ROOT'] ?? '') . '/' . $ruta, __DIR__ . '/../../public/' . $ruta] as $abs) {
        if (is_file($abs)) {
            return $ruta . '?v=' . filemtime($abs);
        }
    }

    return $ruta . '?v=1';
}

function componente(string $__nombre, array $__props = []): void
{
    if (preg_match('/^[a-z0-9_]+$/', $__nombre) !== 1) {
        throw new InvalidArgumentException("Componente inválido: {$__nombre}");
    }

    // EXTR_SKIP protege $__nombre y $__props de un prop que se llame igual.
    extract($__props, EXTR_SKIP);

    require __DIR__ . '/' . $__nombre . '.php';
}
