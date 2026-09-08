document.addEventListener('DOMContentLoaded', () => {
    console.log('evaluacion_docente - listo');
    inicializarDualbox();
});

/**
 * Dual list box para la asignación N:N (permisos de un grupo, usuarios de un
 * grupo). Mueve elementos entre la lista "disponibles" y la lista "asignados"
 * con los botones del centro, con doble clic o arrastrando. Al enviar el
 * formulario, cada elemento de la lista derecha se manda como un ajenos[].
 */
function inicializarDualbox() {
    const caja = document.querySelector('.dualbox');

    if (!caja) {
        return;
    }

    const nombre = caja.dataset.nombre || 'ajenos';
    const listas = caja.querySelectorAll('.dualbox-lista');
    const origen = caja.querySelector('[data-lado="disponibles"]');
    const destino = caja.querySelector('[data-lado="asignados"]');
    const form = caja.closest('form');

    // Inserta un <li> en la lista respetando el orden original (data-orden).
    const insertarOrdenado = (item, lista) => {
        const orden = Number(item.dataset.orden);
        const hermanos = lista.querySelectorAll('.dualbox-item');

        for (const hermano of hermanos) {
            if (Number(hermano.dataset.orden) > orden) {
                lista.insertBefore(item, hermano);
                return;
            }
        }

        lista.appendChild(item);
    };

    const mover = (item, lista) => {
        item.classList.remove('sel');
        insertarOrdenado(item, lista);
    };

    const moverSeleccion = (desde, hacia) => {
        desde.querySelectorAll('.dualbox-item.sel').forEach((item) => mover(item, hacia));
    };

    // --- Selección con clic ---
    listas.forEach((lista) => {
        lista.addEventListener('click', (e) => {
            const item = e.target.closest('.dualbox-item');

            if (!item) {
                return;
            }

            if (!e.ctrlKey && !e.metaKey) {
                lista.querySelectorAll('.dualbox-item.sel').forEach((otro) => {
                    if (otro !== item) {
                        otro.classList.remove('sel');
                    }
                });
            }

            item.classList.toggle('sel');
        });

        // Doble clic: al otro lado de una vez.
        lista.addEventListener('dblclick', (e) => {
            const item = e.target.closest('.dualbox-item');

            if (item) {
                mover(item, lista === origen ? destino : origen);
            }
        });
    });

    // --- Botones del centro ---
    caja.querySelector('[data-accion="asignar"]').addEventListener('click', () => {
        moverSeleccion(origen, destino);
    });

    caja.querySelector('[data-accion="quitar"]').addEventListener('click', () => {
        moverSeleccion(destino, origen);
    });

    // --- Arrastrar y soltar ---
    let arrastrado = null;

    listas.forEach((lista) => {
        lista.addEventListener('dragstart', (e) => {
            arrastrado = e.target.closest('.dualbox-item');

            if (arrastrado && e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                // Firefox no dispara 'drop' sin datos en la transferencia.
                e.dataTransfer.setData('text/plain', arrastrado.dataset.id || '');
            }
        });

        lista.addEventListener('dragover', (e) => {
            e.preventDefault();

            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'move';
            }

            lista.classList.add('dualbox-lista--sobre');
        });

        lista.addEventListener('dragleave', () => {
            lista.classList.remove('dualbox-lista--sobre');
        });

        lista.addEventListener('drop', (e) => {
            e.preventDefault();
            lista.classList.remove('dualbox-lista--sobre');

            if (!arrastrado || arrastrado.parentElement === lista) {
                return;
            }

            // Si el que se arrastra estaba en una selección, se mueve toda.
            if (arrastrado.classList.contains('sel')) {
                moverSeleccion(arrastrado.parentElement, lista);
            } else {
                mover(arrastrado, lista);
            }

            arrastrado = null;
        });
    });

    // --- Envío: un ajenos[] por cada elemento asignado ---
    if (form) {
        form.addEventListener('submit', () => {
            form.querySelectorAll('input[data-dualbox]').forEach((input) => input.remove());

            destino.querySelectorAll('.dualbox-item').forEach((item) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = nombre + '[]';
                input.value = item.dataset.id || '';
                input.setAttribute('data-dualbox', '');
                form.appendChild(input);
            });
        });
    }
}
