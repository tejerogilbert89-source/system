<?php
/**
 * Every route this feature owns: [HTTP method, URL path, file to run].
 * __DIR__ makes the path absolute so it works no matter where it's included from.
 */
return [
    ['GET',  '/login',  __DIR__ . '/pages/login.php'],
    ['POST', '/login',  __DIR__ . '/pages/login.php'],
    ['GET',  '/logout', __DIR__ . '/pages/logout.php'],
];
