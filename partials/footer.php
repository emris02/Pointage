<?php
// PARTIAL FOOTER : scripts JS, fermeture du body et HTML
?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
    // Base-aware script include to avoid rewrite/relative path issues
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    ?>
    <script src="<?= $basePath ?>/assets/js/main.js"></script>
    <?php if (isset($additionalJS)): foreach ($additionalJS as $js): 
            $isExternal = preg_match('#^https?://#i', $js);
            if ($isExternal) {
                $src = $js;
                $typeAttr = '';
            } else {
                // Resolve base path for browser and filesystem path for inspection
                $src = $basePath . '/' . ltrim($js, '/');
                $localFile = __DIR__ . '/../' . ltrim($js, '/');
                $typeAttr = '';
                if (file_exists($localFile)) {
                    $contents = file_get_contents($localFile);
                    if (strpos($contents, 'import ') !== false || strpos($contents, 'export ') !== false) {
                        // Load as ES module when the file uses import/export
                        $typeAttr = ' type="module"';
                    }
                }
            }
    ?>
        <script<?= $typeAttr ?> src="<?= htmlspecialchars($src) ?>"></script>
    <?php endforeach; endif; ?>
    <?php if (isset($inlineJS)): ?>
        <script><?= $inlineJS ?></script>
    <?php endif; ?>
</body>
</html>
<!-- Fin du partial footer -->
