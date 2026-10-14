/**
 * Editor de texto con formato para los campos 'html' de tables.php (Quill 2,
 * en assets/js/vendor). El valor viaja en el <input hidden> del campo: aquí
 * solo se copia el HTML del editor al enviar. El servidor lo vuelve a limpiar
 * con limpiaHtml(), así que la barra es comodidad, no barrera.
 */
document.querySelectorAll('.campo-html').forEach((campo) => {
    const input = campo.querySelector('input[type="hidden"]');

    const quill = new Quill(campo.querySelector('.editor-html'), {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ script: 'sub' }, { script: 'super' }],
                [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
                [{ align: [] }],
                ['link', 'clean'],
            ],
        },
        // Lo mismo que deja pasar limpiaHtml(): así lo que se pega (colores,
        // imágenes, tablas) no desaparece hasta después de guardar.
        formats: ['header', 'bold', 'italic', 'underline', 'strike', 'script', 'list', 'indent', 'align', 'link'],
    });

    input.form.addEventListener('submit', () => {
        // getSemanticHTML() de Quill 2.0.3 convierte cada espacio en &nbsp;,
        // y con eso el texto ya no parte renglones.
        input.value = quill.getText().trim() === ''
            ? ''
            : quill.getSemanticHTML().replaceAll('&nbsp;', ' ');
    });
});
