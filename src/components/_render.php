<?php

declare(strict_types=1);

/**
 * Pinta un componente de src/components/.
 *
 *   componente('tabla', ['cfg' => $cfg, 'filas' => $filas]);
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
function componente(string $__nombre, array $__props = []): void
{
    if (preg_match('/^[a-z0-9_]+$/', $__nombre) !== 1) {
        throw new InvalidArgumentException("Componente inválido: {$__nombre}");
    }

    // EXTR_SKIP protege $__nombre y $__props de un prop que se llame igual.
    extract($__props, EXTR_SKIP);

    require __DIR__ . '/' . $__nombre . '.php';
}
