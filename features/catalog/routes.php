<?php
return [
    ['GET',  '/catalog',      __DIR__ . '/pages/index.php'],
    ['GET',  '/catalog/new',  __DIR__ . '/pages/form.php'],
    ['POST', '/catalog/new',  __DIR__ . '/pages/form.php'],
    ['GET',  '/catalog/edit', __DIR__ . '/pages/form.php'],
    ['POST', '/catalog/edit', __DIR__ . '/pages/form.php'],
    ['POST', '/catalog/copy', __DIR__ . '/pages/copy.php'],
];
