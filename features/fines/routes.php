<?php
return [
    ['GET',  '/fines',        __DIR__ . '/pages/index.php'],
    ['GET',  '/fines/patron', __DIR__ . '/pages/patron.php'],
    ['POST', '/fines/pay',    __DIR__ . '/pages/pay.php'],
    ['POST', '/fines/waive',  __DIR__ . '/pages/waive.php'],
];
