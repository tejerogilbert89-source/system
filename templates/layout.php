<?php
/**
 * Page shell. index.php sets $pageFile, then requires this file.
 * We capture the page's own HTML in a buffer first, so the page is free
 * to redirect (header('Location: ...'); exit;) before anything is sent
 * to the browser.
 *
 * A page can set $layout = 'staff'; as the very first thing it does
 * (before any HTML) to get the sidebar chrome instead of the default
 * top-nav chrome. See features/dashboard/pages/index.php for an example.
 */
$layout = 'public';

ob_start();
require $pageFile;
$content = ob_get_clean();

if ($layout === 'staff') {
    require __DIR__ . '/staff-header.php';
    echo $content;
    require __DIR__ . '/staff-footer.php';
} else {
    require __DIR__ . '/header.php';
    echo '<main class="public-content">';
    echo $content;
    echo '</main>';
    require __DIR__ . '/footer.php';
}
