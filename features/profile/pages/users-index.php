<?php
/**
 * Users - admin only. Every account in the system (patrons, librarians,
 * admins), minus the currently logged-in admin's own row.
 *
 * Password rule: a new account's starting password is always its ID
 * number. Patron ID numbers are 7 digits; librarian and admin ID numbers
 * are 5 digits. Account holders change it later from My Profile.
 */
$layout = 'staff';
Auth::requireRole(['admin']);

$users      = User::all(Auth::id());
$roles      = User::creatableRoles();
$categories = User::patronCategories();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<h1>Users</h1>
<p class="muted">A new account's starting password is always its ID number - patrons use 7 digits, librarians and admins use 5.</p>

<?php if ($flash): ?>
    <p class="<?= $flash['type'] === 'error' ? 'error' : 'muted' ?>"><?= htmlspecialchars($flash['text']) ?></p>
<?php endif; ?>

<div class="section-heading">
    <h2>Create account</h2>
</div>

<div class="card" style="max-width: 640px;">
    <form method="post" action="/users/create" id="create-user-form">
        <?= Csrf::field() ?>

        <label>
            Role
            <select name="role_id" id="role-select" required>
                <option value="">Choose a role&hellip;</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>" data-role-name="<?= htmlspecialchars($role['name']) ?>">
                        <?= htmlspecialchars(ucfirst($role['name'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            ID number
            <input type="text" name="id_number" id="id-number" inputmode="numeric" pattern="\d*" autocomplete="off" required>
        </label>
        <p class="muted" id="id-hint" style="margin-top: -0.75em; font-size: 0.85em;">Pick a role first.</p>

        <label>
            Full name
            <input type="text" name="full_name" maxlength="150" required>
        </label>

        <label>
            Email (optional)
            <input type="email" name="email" maxlength="150">
        </label>

        <div id="patron-fields" hidden>
            <label>
                Patron category
                <select name="patron_category_id" id="patron-category">
                    <option value="">Choose a category&hellip;</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Program / department (optional)
                <input type="text" name="program" maxlength="100">
            </label>
            <label>
                Year level (optional)
                <input type="text" name="year_level" maxlength="20">
            </label>
            <label>
                Contact number (optional)
                <input type="text" name="contact_number" maxlength="30">
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Create account</button>
    </form>
</div>

<div class="section-heading">
    <h2>All accounts</h2>
</div>

<table>
    <thead>
        <tr><th>Name</th><th>ID number</th><th>Role</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (!$users): ?>
            <tr><td colspan="5" class="muted">No other accounts yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['id_number']) ?></td>
                <td><?= htmlspecialchars(ucfirst($u['role_name'])) ?></td>
                <td>
                    <?php if ($u['status'] === 'active'): ?>
                        <span class="badge badge-available">Active</span>
                    <?php elseif ($u['status'] === 'suspended'): ?>
                        <span class="badge badge-overdue">Suspended</span>
                    <?php else: ?>
                        <span class="badge badge-out">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="/users/status" style="display:inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <?php if ($u['status'] === 'active'): ?>
                            <input type="hidden" name="status" value="suspended">
                            <button type="submit" class="btn btn-ghost">Suspend</button>
                        <?php else: ?>
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-ghost">Reactivate</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
(function () {
    var idLengths = {};
    <?php foreach ($roles as $role): ?>
        idLengths[<?= json_encode((string) $role['id']) ?>] = <?= (int) \User::idLength($role['name']) ?>;
    <?php endforeach; ?>

    var roleSelect    = document.getElementById('role-select');
    var idInput       = document.getElementById('id-number');
    var idHint        = document.getElementById('id-hint');
    var patronFields  = document.getElementById('patron-fields');
    var patronCat     = document.getElementById('patron-category');

    function isPatronSelected() {
        var opt = roleSelect.options[roleSelect.selectedIndex];
        return !!opt && (opt.getAttribute('data-role-name') || '').toLowerCase() === 'patron';
    }

    // Runs on change AND once on load (browsers restore a previously chosen
    // role after Back/refresh without firing "change").
    function sync() {
        var len = idLengths[roleSelect.value];
        if (len) {
            // Exact length enforced by the browser too, not just the server.
            idInput.setAttribute('maxlength', len);
            idInput.setAttribute('minlength', len);
            idInput.setAttribute('pattern', '\\d{' + len + '}');
            idInput.setAttribute('title', 'Exactly ' + len + ' digits (numbers only).');
            idHint.textContent = 'Exactly ' + len + ' digits.';
        } else {
            idInput.removeAttribute('maxlength');
            idInput.removeAttribute('minlength');
            idInput.setAttribute('pattern', '\\d*');
            idInput.removeAttribute('title');
            idHint.textContent = 'Pick a role first.';
        }
        var patron = isPatronSelected();
        patronFields.hidden = !patron;
        // A hidden "required" field would block the form with no visible error.
        patronCat.required = patron;
    }

    roleSelect.addEventListener('change', sync);
    sync();
}());
</script>