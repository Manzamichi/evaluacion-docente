/**
 * Arrastrar y soltar para las listas .ordenable (preguntas de un instrumento).
 * Mueve el <li> mientras se arrastra y, al soltarlo en otro lugar, envía el
 * formulario: los <input name="orden[]"> ya van en el orden nuevo y el servidor
 * renumera. Sin esto quedan los botones ↑ ↓, que envían solos.
 */
document.querySelectorAll('.ordenable').forEach((lista) => {
    const form = lista.closest('form');
    const ordenActual = () => [...lista.children].map((li) => li.dataset.id).join(',');

    let arrastrada = null;
    let antes = '';

    lista.addEventListener('dragstart', (ev) => {
        arrastrada = ev.target.closest('li');
        antes = ordenActual();
        arrastrada.classList.add('arrastrando');
        ev.dataTransfer.effectAllowed = 'move';
    });

    lista.addEventListener('dragover', (ev) => {
        ev.preventDefault();
        const sobre = ev.target.closest('li');

        if (!arrastrada || !sobre || sobre === arrastrada) {
            return;
        }

        // Mitad de arriba: va antes; mitad de abajo: después.
        const caja = sobre.getBoundingClientRect();
        const despues = ev.clientY > caja.top + caja.height / 2;
        lista.insertBefore(arrastrada, despues ? sobre.nextSibling : sobre);
    });

    lista.addEventListener('drop', (ev) => ev.preventDefault());

    lista.addEventListener('dragend', () => {
        arrastrada.classList.remove('arrastrando');

        if (ordenActual() !== antes) {
            form.elements.foco.value = arrastrada.dataset.id;
            lista.classList.add('guardando');
            form.requestSubmit();
        }

        arrastrada = null;
    });
});
