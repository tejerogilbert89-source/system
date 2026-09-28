<?php
/**
 * Login form markup only - no <html> chrome. Included by:
 *   - features/auth/pages/login.php (the full /login page - also where
 *     the POST is actually handled; this file only renders)
 *   - templates/header.php (inside the login modal, on every top-nav page)
 *
 * Expects $error to be set (or null) by whichever page includes it.
 */
?>
<div class="auth-box">
    <h1><?= htmlspecialchars(APP_NAME) ?></h1>
    <h2>Log In</h2>

    <?php if ($error ?? null): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/login" id="login-form">
        <?= Csrf::field() ?>

        <label>
            ID Number
            <input type="text" name="id_number" required autofocus
                   value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
        </label>

        <label>
            Password
            <input type="password" name="password" required>
        </label>

        <button type="submit" class="btn btn-primary">Log In</button>
    </form>
</div>