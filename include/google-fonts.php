<?php

declare(strict_types=1);

/**
 * Shared Google Fonts bundle (preconnect + one css2 request).
 * Add --font-* variables for use in page stylesheets.
 */
$gf = 'https://fonts.googleapis.com/css2?family='
    . 'DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400'
    . '&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400'
    . '&family=Outfit:wght@300;400;500;600;700;800'
    . '&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400'
    . '&family=Space+Grotesk:wght@400;500;600;700'
    . '&family=Sora:wght@400;500;600;700'
    . '&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400'
    . '&display=swap';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= htmlspecialchars($gf, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<style>
:root {
  --font-body: "DM Sans", system-ui, sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, monospace;
  --font-display: "Outfit", "Space Grotesk", system-ui, sans-serif;
  --font-ui: "Plus Jakarta Sans", "DM Sans", system-ui, sans-serif;
  --font-accent: "Sora", "Plus Jakarta Sans", sans-serif;
  --font-serif: "Libre Baskerville", Georgia, "Times New Roman", serif;
}
</style>
