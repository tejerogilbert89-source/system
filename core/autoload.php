<?php
/**
 * Loads a class file automatically the first time the class is used,
 * so we don't have to write require/include for every single file.
 *
 * It looks for the class in two places:
 *   1. core/            (Database.php, Auth.php, Csrf.php, Audit.php ...)
 *   2. features/       (e.g. features/circulation/Loan.php, once that
 *                          feature exists)
 */
spl_autoload_register(function (string $class): void {
    $searchDirs = [__DIR__]; // core/

    $featuresDir = dirname(__DIR__) . '/features';
    foreach (glob($featuresDir . '/*', GLOB_ONLYDIR) as $featureDir) {
        $searchDirs[] = $featureDir;
    }

    foreach ($searchDirs as $dir) {
        $file = $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
