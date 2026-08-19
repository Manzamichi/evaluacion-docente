<?php

declare(strict_types=1);

/**
 * Pie compartido por todas las vistas.
 *
 * Variable opcional que la vista puede definir antes de incluirlo:
 *   $js  string[]  Scripts extra dentro de public/assets/js/
 */
?>
</main>

<script src="assets/js/app.js"></script>
<?php foreach ($js ?? [] as $script): ?>
    <script src="assets/js/<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>