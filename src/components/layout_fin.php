<?php

declare(strict_types=1);

/**
 * Cierre de la página. Pareja de 'layout_inicio'.
 *
 * Props:
 *   js  string[]  Scripts extra dentro de public/assets/js/  (opcional)
 */
?>
    </main>
</div>

<script src="<?= e(assetVer('assets/js/app.js')) ?>"></script>
<?php foreach ($js ?? [] as $script): ?>
    <script src="<?= e(assetVer('assets/js/' . $script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
