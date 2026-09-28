<?php
/**
 * Users - admin only. Every account in the system (patrons, librarians,
 * admins), minus the currently logged-in admin's own row.
 */
$layout = 'staff';
Auth::requireRole(['admin']);
?>
<h1>Users</h1>
<p class="muted">This screen hasn't been designed yet - check back soon.</p>
