<?php

declare(strict_types=1);

/**
 * Saneo del HTML que manda el editor de texto (campos 'html' de tables.php).
 *
 * Es una lista blanca: una etiqueta que no está en HTML_ETIQUETAS se quita y su
 * texto se conserva; un atributo que no se reconoce se borra. Lo que queda es
 * seguro para imprimirse sin e(), y es lo único que se imprime así.
 *
 * Se limpia al guardar (saneaEntrada) y otra vez al mostrar: la base también se
 * llena desde CSV, phpMyAdmin o un sistema anterior.
 */

/** Lo que produce el editor (Quill): formato de texto, encabezados, listas y enlaces. */
const HTML_ETIQUETAS = ['p', 'br', 'strong', 'em', 'u', 's', 'sub', 'sup', 'h1', 'h2', 'h3', 'ol', 'ul', 'li', 'a'];

/** Se borran con todo y contenido: su texto no es texto para quien lee. */
const HTML_DESCARTAR = ['script', 'style', 'template', 'iframe', 'object', 'embed', 'noscript', 'svg', 'math', 'textarea', 'select', 'title'];

/** Alineación y sangría de Quill: son clases, no style, así que basta una lista. */
const HTML_CLASES = '/^ql-(align-(center|right|justify)|indent-[1-8])$/';

/**
 * HTML seguro, o '' si no tiene texto (un editor vacío manda "<p></p>" y eso
 * no debe pasar por un campo requerido lleno).
 */
function limpiaHtml(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $doc = Dom\HTMLDocument::createFromString(
        '<!DOCTYPE html><body>' . $html,
        LIBXML_NOERROR,
        'UTF-8'
    );

    limpiaNodo($doc->body);

    return trim($doc->body->textContent) === '' ? '' : $doc->body->innerHTML;
}

/**
 * Una sola línea de texto, para el listado. Los bloques (párrafos, renglones de
 * lista) se separan con espacio; las etiquetas en línea no, o "<b>for</b>ma"
 * saldría partida.
 */
function textoPlano(string $html): string
{
    $html  = preg_replace('#<(/?(p|li|h[1-6]|div|ul|ol)|br)\b#i', ' $0', $html);
    $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', $texto));
}

function limpiaNodo(Dom\Element $padre): void
{
    // Copia de la lista: se va a modificar mientras se recorre.
    foreach (iterator_to_array($padre->childNodes) as $nodo) {
        if ($nodo instanceof Dom\Text) {
            continue;
        }

        // Comentarios y cualquier otro nodo que no sea elemento.
        if (!$nodo instanceof Dom\Element || in_array($nodo->localName, HTML_DESCARTAR, true)) {
            $nodo->remove();
            continue;
        }

        limpiaNodo($nodo);

        // <div>, <span>, <font>...: se va la etiqueta, se queda su texto ya limpio.
        if ($nodo->namespaceURI !== 'http://www.w3.org/1999/xhtml'
            || !in_array($nodo->localName, HTML_ETIQUETAS, true)) {
            $nodo->replaceWith(...iterator_to_array($nodo->childNodes));
            continue;
        }

        limpiaAtributos($nodo);
    }
}

function limpiaAtributos(Dom\Element $el): void
{
    foreach (iterator_to_array($el->attributes) as $attr) {
        $nombre = $attr->name;

        if ($nombre === 'class') {
            $clases = array_filter(
                preg_split('/\s+/', $attr->value),
                static fn (string $c): bool => preg_match(HTML_CLASES, $c) === 1
            );

            if ($clases !== []) {
                $el->setAttribute('class', implode(' ', $clases));
                continue;
            }
        }

        // Solo esquemas conocidos: así no pasa javascript:, data: ni variantes
        // con espacios o caracteres de control en medio.
        if ($nombre === 'href' && $el->localName === 'a'
            && preg_match('#^(https?://|mailto:)#i', trim($attr->value)) === 1) {
            continue;
        }

        $el->removeAttribute($nombre);
    }

    if ($el->localName === 'a') {
        $el->setAttribute('target', '_blank');
        $el->setAttribute('rel', 'noopener noreferrer');
    }
}
