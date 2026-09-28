<?php
return [
    ['GET',  '/circulation',          __DIR__ . '/pages/index.php'],
    ['POST', '/circulation/checkout', __DIR__ . '/pages/checkout.php'],
    ['GET',  '/circulation/loans',    __DIR__ . '/pages/loans.php'],
    ['POST', '/circulation/return',   __DIR__ . '/pages/return.php'],
    ['POST', '/circulation/renew',    __DIR__ . '/pages/renew.php'],
];