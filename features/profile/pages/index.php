<?php
/**
 * My Profile - available to every logged-in role (patron, librarian, admin).
 * Uses whichever chrome matches the current user: sidebar for staff,
 * top nav for patrons - same page, different frame around it.
 *
 * Identity is proven by the session (Auth::id()) - the ID number is never
 * retyped here, and the account being changed is always the one that's
 * currently logged in. Shows read-only account details plus a working
 * change-password form.
 */
Auth::requireLogin();
$layout = in_array(Auth::role(), ['admin', 'librarian'], true) ? 'staff' : 'public';

$pdo = Database::connection();

$stmt = $pdo->prepare(
    "SELECT u.id, u.id_number, u.full_name, r.name AS role_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.id = ?
     LIMIT 1"
);
$stmt->execute([Auth::id()]);
$me = $stmt->fetch();

if (!$me) {
    // Session points at a user that no longer exists (e.g. deleted mid-session).
    Auth::logout();
    header('Location: /login');
    exit;
}

// Kept in a separate query (not the SELECT above) purely so the password
// hash never travels alongside display data - it's only ever read right
// before it's needed, in the POST branch below.
function currentPasswordHash(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return (string) $stmt->fetchColumn();
}

$error   = null;
$success = null;

// Password-change limits. The cap on the new password stops someone from
// making the server hash a megabyte-sized "password"; the throttle slows
// down guessing of the current password from a hijacked/unattended session.
const PW_MAX_LENGTH   = 128;
const PW_MAX_CURRENT  = 4096;
const PW_MAX_FAILS    = 5;
const PW_LOCK_SECONDS = 300;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ((int) ($_SESSION['pw_lock_until'] ?? 0) > time()) {
            $error = 'Too many wrong attempts. Please wait a few minutes and try again.';
        } elseif (!is_string($current) || !is_string($new) || !is_string($confirm)) {
            // Tampered form (e.g. "new_password[]=x") - would crash password_verify().
            $error = 'Something was wrong with the form. Please try again.';
        } elseif ($current === '' || $new === '' || $confirm === '') {
            $error = 'Fill in every field before saving.';
        } elseif (strlen($new) < 8) {
            $error = 'The new password needs at least 8 characters.';
        } elseif (strlen($new) > PW_MAX_LENGTH) {
            $error = 'The new password can be at most ' . PW_MAX_LENGTH . ' characters.';
        } elseif ($new !== $confirm) {
            $error = 'The two new passwords do not match. Type them again.';
        } elseif ($new === $me['id_number']) {
            $error = 'The new password cannot be your ID number. Pick something else.';
        } else {
            $hash = currentPasswordHash($pdo, (int) $me['id']);

            if (strlen($current) > PW_MAX_CURRENT || !password_verify($current, $hash)) {
                $_SESSION['pw_fails'] = (int) ($_SESSION['pw_fails'] ?? 0) + 1;
                if ($_SESSION['pw_fails'] >= PW_MAX_FAILS) {
                    $_SESSION['pw_lock_until'] = time() + PW_LOCK_SECONDS;
                    $_SESSION['pw_fails']      = 0;
                }
                $error = 'The current password is wrong.';
            } elseif (password_verify($new, $hash)) {
                $error = 'That is already your current password. Pick a different one.';
            } else {
                $newHash = password_hash($new, PASSWORD_ARGON2ID);
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([$newHash, $me['id']]);

                Audit::log(Auth::id(), 'password_change', 'users', $me['id']);

                unset($_SESSION['pw_fails'], $_SESSION['pw_lock_until']);
                // New credentials -> new session id, so a stolen old session id is useless.
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }

                $success = 'Password changed. Use the new one next time you log in.';
            }
        }
    }
}

/** One floating-book SVG, positioned/sized/timed by the caller. */
function floatingBook(string $top, string $left, int $size, float $delay, float $duration, float $rotate): string
{
    return '<svg class="floating-book" style="top:' . $top . ';left:' . $left . ';width:' . $size . 'px;height:' . round($size * 0.8) . 'px;
                animation-delay:' . $delay . 's;animation-duration:' . $duration . 's;--tilt:' . $rotate . 'deg;"
                viewBox="0 0 42 34" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M21 6.5C17 3.4 10 2.3 3 3.2v23.4c7-.9 14 .1 18 3.2 4-3.1 11-4.1 18-3.2V3.2c-7-.9-14 .2-18 3.3z" fill="#ffffff"/>
        <path d="M21 6.5v26.3" stroke="rgba(27,67,50,0.25)" stroke-width="1"/>
    </svg>';
}
?>
<style>
    /* ---- Profile hero: pine-to-moss gradient with softly floating books ---- */
    .profile-hero {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, var(--pine) 0%, var(--moss) 100%);
        border-radius: var(--radius);
        padding: 2.75rem 2rem;
        margin-bottom: 2rem;
        isolation: isolate;
    }
    .profile-hero h1 { color: #fff; margin-bottom: 0.35rem; }
    .profile-hero p.lead { color: rgba(255, 255, 255, 0.85); margin: 0; max-width: 46ch; }
    .profile-hero .role-badge {
        display: inline-block;
        margin-top: 1rem;
        padding: 0.3rem 0.85rem;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 999px;
        color: #fff;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.03em;
    }
    .floating-book {
        position: absolute;
        opacity: 0.5;
        animation-name: bookFloat;
        animation-timing-function: ease-in-out;
        animation-iteration-count: infinite;
        transform: rotate(var(--tilt));
        z-index: -1;
    }
    @keyframes bookFloat {
        0%, 100% { transform: translateY(0) rotate(var(--tilt)); }
        50%      { transform: translateY(-16px) rotate(calc(var(--tilt) * -1)); }
    }
    @media (prefers-reduced-motion: reduce) {
        .floating-book { animation: none; }
    }

    /* ---- Section heading with a small book mark ---- */
    .profile-section-title {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin: 0 0 1.1rem;
    }
    .profile-section-title svg { flex-shrink: 0; }

    /* ---- Cards ---- */
    .profile-grid {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }
    .profile-card {
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        padding: 1.75rem;
        box-shadow: 0 1px 3px rgba(27, 67, 50, 0.06);
    }
    .field-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        padding: 0.7rem 0;
        border-bottom: 1px solid var(--line);
    }
    .field-row:last-child { border-bottom: none; }
    .field-row .field-label { color: #6b6f6a; font-size: 0.85rem; }
    .field-row .field-value { font-weight: 600; text-align: right; }

    /* ---- Page wrapper: keeps hero + cards the same centered width on
       every layout, so this page looks identical whether it's wrapped in
       the full-bleed staff shell or the already-centered public shell. ---- */
    .profile-page { max-width: 900px; margin: 0 auto; }

    .pw-field { position: relative; }
    .pw-field input { padding-right: 3.1rem; }
    .pw-toggle {
        position: absolute;
        right: 0.6rem;
        top: 2.15rem;
        background: none;
        border: none;
        color: var(--moss);
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        padding: 0.2rem 0.3rem;
    }
    .pw-toggle:hover { text-decoration: underline; }
    .pw-hint { margin-top: -0.75em; font-size: 0.85em; }
</style>

<div class="profile-page">
<section class="profile-hero">
    <?= floatingBook('10%', '8%', 40, 0.0, 6.5, -8) ?>
    <?= floatingBook('60%', '18%', 30, 1.2, 5.5, 10) ?>
    <?= floatingBook('20%', '85%', 46, 0.6, 7.0, 6) ?>
    <?= floatingBook('68%', '90%', 32, 1.8, 6.0, -12) ?>
    <?= floatingBook('40%', '50%', 26, 0.9, 5.8, 4) ?>

    <h1>My Profile</h1>
    <p class="lead">Manage your account details and keep your password up to date.</p>
    <span class="role-badge"><?= htmlspecialchars(ucfirst($me['role_name'])) ?></span>
</section>

<div class="profile-grid">

    <div class="profile-card">
        <h2 class="profile-section-title">
            <svg width="22" height="18" viewBox="0 0 42 34" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M21 6.5C17 3.4 10 2.3 3 3.2v23.4c7-.9 14 .1 18 3.2 4-3.1 11-4.1 18-3.2V3.2c-7-.9-14 .2-18 3.3z" fill="var(--pine)"/>
            </svg>
            Account
        </h2>
        <div class="field-row">
            <span class="field-label">Name</span>
            <span class="field-value"><?= htmlspecialchars($me['full_name']) ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">ID number</span>
            <span class="field-value"><?= htmlspecialchars($me['id_number']) ?></span>
        </div>
        <div class="field-row">
            <span class="field-label">Role</span>
            <span class="field-value"><?= htmlspecialchars(ucfirst($me['role_name'])) ?></span>
        </div>
    </div>

    <div class="profile-card">
        <h2 class="profile-section-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect x="5" y="11" width="14" height="10" rx="2" stroke="var(--pine)" stroke-width="1.8"/>
                <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="var(--pine)" stroke-width="1.8"/>
            </svg>
            Change password
        </h2>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php elseif ($success): ?>
            <p class="muted"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form method="post" action="/profile">
            <?= Csrf::field() ?>

            <label class="pw-field">
                Current password
                <input type="password" name="current_password" id="pw-current" autocomplete="current-password" required>
                <button type="button" class="pw-toggle" data-target="pw-current">Show</button>
            </label>

            <label class="pw-field">
                New password
                <input type="password" name="new_password" id="pw-new" autocomplete="new-password" required minlength="8" maxlength="128">
                <button type="button" class="pw-toggle" data-target="pw-new">Show</button>
            </label>
            <p class="muted pw-hint">At least 8 characters, and not your ID number.</p>

            <label class="pw-field">
                Repeat new password
                <input type="password" name="confirm_password" id="pw-confirm" autocomplete="new-password" required minlength="8" maxlength="128">
                <button type="button" class="pw-toggle" data-target="pw-confirm">Show</button>
            </label>

            <button type="submit" class="btn btn-primary">Save new password</button>
        </form>
    </div>

</div>
</div>

<script>
(function () {
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-target'));
            if (!input) return;
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.textContent = isHidden ? 'Hide' : 'Show';
        });
    });
}());
</script>