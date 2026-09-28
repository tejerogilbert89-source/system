<?php
/**
 * Logs the audit entry BEFORE destroying the session,
 * since Auth::id() needs the session to still be there.
 */
Audit::log(Auth::id(), 'logout', 'users', Auth::id());
Auth::logout();
header('Location: http://lms.local/');
exit;
