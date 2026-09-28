<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Staff</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/shared/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="staff-menu" aria-label="Staff navigation" aria-hidden="true">
        <div class="sidebar-header">
            <span class="brand">LMS Staff</span>
            <button type="button" class="opac-menu-close" id="staff-menu-close" aria-label="Close staff navigation">&times;</button>
        </div>
        <nav>
            <?php
            // $path comes from public/index.php - shared scope, since every
            // require in this chain runs in the same scope, not a new one.
            //
            // Admin and librarian have separate, non-overlapping sets of
            // day-to-day screens. Reports and Audit Log are the two "oversight"
            // screens both roles get - everything else is role-specific.
            if (Auth::role() === 'admin') {
                $navItems = [
                    '/dashboard' => 'Dashboard',
                    '/reports'   => 'Reports',
                    '/audit'     => 'Audit Log',
                    '/users'     => 'Users',
                    '/settings'  => 'Settings',
                ];
            } else {
                $navItems = [
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
            }
            foreach ($navItems as $href => $label):
                $activeClass = ($path === $href) ? ' active' : '';
            ?>
                <a href="<?= $href ?>" class="<?= trim($activeClass) ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <div class="main-content">
        <nav class="navbar">
            <div class="nav-left">
                <button type="button" class="menu-toggle" id="staff-menu-toggle" aria-expanded="false" aria-controls="staff-menu" aria-label="Open staff navigation">
                    <span aria-hidden="true">&#9776;</span>
                </button>
                <span class="brand"><?= htmlspecialchars(APP_NAME) ?></span>
            </div>
            <div class="nav-right">
                <span class="nav-user"><?= htmlspecialchars(Auth::name()) ?> (<?= htmlspecialchars(Auth::role()) ?>)</span>
                <a href="/profile">My Profile</a>
                <a href="/logout">Log out</a>
            </div>
        </nav>
        <div class="subnav">
            <div class="subnav-inner">
                <a href="/">Catalog</a>
                <a href="/digital">Digital Library</a>
            </div>
        </div>
        <div class="staff-menu-backdrop" id="staff-menu-backdrop" hidden></div>
        <script>
        (function () {
            var toggle = document.getElementById('staff-menu-toggle');
            var menu = document.getElementById('staff-menu');
            var close = document.getElementById('staff-menu-close');
            var backdrop = document.getElementById('staff-menu-backdrop');
            if (!toggle || !menu || !close || !backdrop) return;

            function setMenuOpen(isOpen) {
                document.body.classList.toggle('staff-menu-open', isOpen);
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