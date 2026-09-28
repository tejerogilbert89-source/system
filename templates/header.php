<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/shared/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-left">
        <button type="button" class="menu-toggle" id="opac-menu-toggle" aria-expanded="false" aria-controls="opac-menu" aria-label="Open staff navigation">
            <span aria-hidden="true">&#9776;</span>
        </button>
        <span class="brand"><?= htmlspecialchars(APP_NAME) ?></span>
    </div>
    <div class="nav-right">
        <?php if (Auth::check()): ?>
            <span class="nav-user"><?= htmlspecialchars(Auth::name()) ?> (<?= htmlspecialchars(Auth::role()) ?>)</span>
            <a href="/profile">My Profile</a>
            <a href="/logout">Log out</a>
        <?php else: ?>
            <a href="/login" id="login-trigger">Log in</a>
        <?php endif; ?>
    </div>
</nav>
<?php
$isCatalogPage = ($path ?? '') === '/' || str_starts_with($path ?? '', '/opac');
$isDigitalPage = str_starts_with($path ?? '', '/digital');
?>
<div class="subnav">
    <div class="subnav-inner">
        <a href="/" class="<?= $isCatalogPage ? 'active' : '' ?>"<?= $isCatalogPage ? ' aria-current="page"' : '' ?>>Catalog</a>
        <a href="/digital" class="<?= $isDigitalPage ? 'active' : '' ?>"<?= $isDigitalPage ? ' aria-current="page"' : '' ?>>Digital Library</a>
    </div>
</div>

<?php
$opacNavItems = Auth::check() && Auth::role() === 'admin'
    ? [
        '/dashboard' => 'Dashboard',
        '/reports'   => 'Reports',
        '/audit'     => 'Audit Log',
        '/users'     => 'Users',
        '/settings'  => 'Settings',
    ]
    : [
        '/dashboard'   => 'Dashboard',
        '/circulation' => 'Circulation',
        '/catalog'     => 'Catalog',
        '/patrons'     => 'Patrons',
        '/holds'       => 'Holds',
        '/fines'       => 'Fines',
        '/inventory'   => 'Inventory',
        '/reports'     => 'Reports',
        '/audit'       => 'Audit Log',
    ];
?>
<aside class="opac-menu" id="opac-menu" aria-label="Staff navigation" aria-hidden="true">
    <div class="opac-menu-header">
        <span class="brand">LMS Staff</span>
        <button type="button" class="opac-menu-close" id="opac-menu-close" aria-label="Close staff navigation">&times;</button>
    </div>
    <nav>
        <?php foreach ($opacNavItems as $href => $label): ?>
            <a href="<?= $href ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="opac-menu-backdrop" id="opac-menu-backdrop" hidden></div>

<script>
(function () {
    var toggle = document.getElementById('opac-menu-toggle');
    var menu = document.getElementById('opac-menu');
    var close = document.getElementById('opac-menu-close');
    var backdrop = document.getElementById('opac-menu-backdrop');
    if (!toggle || !menu || !close || !backdrop) return;

    function setMenuOpen(isOpen) {
        document.body.classList.toggle('opac-menu-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.setAttribute('aria-label', isOpen ? 'Close staff navigation' : 'Open staff navigation');
        menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        backdrop.hidden = !isOpen;
    }

    toggle.addEventListener('click', function () { setMenuOpen(true); });
    close.addEventListener('click', function () { setMenuOpen(false); });
    backdrop.addEventListener('click', function () { setMenuOpen(false); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') setMenuOpen(false);
    });
}());
</script>

<?php if (!Auth::check() && ($_SERVER['REQUEST_URI'] ?? '') !== '/login'): ?>
<dialog id="login-modal">
    <button type="button" class="modal-close" id="login-modal-close" aria-label="Close">&times;</button>
    <?php $error = null; require __DIR__ . '/../features/auth/login-form.php'; ?>
</dialog>
<script>
(function () {
    var trigger = document.getElementById('login-trigger');
    var dialog = document.getElementById('login-modal');
    var closeBtn = document.getElementById('login-modal-close');
    if (!trigger || !dialog) return;

    var form = dialog.querySelector('#login-form');
    var authBox = dialog.querySelector('.auth-box');

    function openModal(e) {
        e.preventDefault();
        dialog.showModal();
        var firstInput = dialog.querySelector('input');
        if (firstInput) firstInput.focus();
    }
    function clearError() {
        var existing = authBox.querySelector('.error');
        if (existing) existing.remove();
    }
    function showError(message) {
        var existing = authBox.querySelector('.error');
        if (existing) {
            existing.textContent = message;
        } else {
            var p = document.createElement('p');
            p.className = 'error';
            p.textContent = message;
            form.insertAdjacentElement('beforebegin', p);
        }
    }

    trigger.addEventListener('click', openModal);
    closeBtn.addEventListener('click', function () { dialog.close(); });

    // Click on the backdrop closes it. Native <dialog> reports these
    // clicks as targeting the dialog itself, so we check actual box
    // bounds rather than e.target - clicking the dialog's own padding
    // also has e.target === dialog, and that shouldn't close it.
    dialog.addEventListener('click', function (e) {
        var box = dialog.getBoundingClientRect();
        var inside = e.clientX >= box.left && e.clientX <= box.right &&
                     e.clientY >= box.top && e.clientY <= box.bottom;
        if (!inside) dialog.close();
    });

    // Covers every way the dialog closes - this button, a backdrop
    // click, and Escape (which <dialog> handles natively - no listener
    // needed for that one).
    dialog.addEventListener('close', function () {
        clearError();
        form.reset();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        var body = new FormData(form);
        body.append('ajax', '1');

        fetch('/login', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    window.location = data.redirect;
                    return;
                }
                submitBtn.disabled = false;
                showError(data.error);
            })
            .catch(function () {
                // Something went wrong with the fetch itself (not a login
                // failure) - fall back to a real page submit rather than
                // leaving the user stuck with a disabled button.
                submitBtn.disabled = false;
                form.submit();
            });
    });
})();
</script>
<?php endif; ?>

<main class="container">