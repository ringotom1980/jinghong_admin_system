<?php
declare(strict_types=1);

/**
 * 專案根目錄 vendor 在： jinghong_admin_system/vendor
 * 本檔位於：                  jinghong_admin_system/Public/modules/mat/Autoloader.php
 * 因此 vendor 的實體路徑：   __DIR__ . '/../../../vendor/'
 */

spl_autoload_register(function (string $class): void {
    $map = [
        // PhpSpreadsheet：vendor/src/PhpSpreadsheet/
        'PhpOffice\\PhpSpreadsheet\\' => [
            __DIR__ . '/../../../vendor/src/PhpSpreadsheet/',
        ],

        // PSR SimpleCache：vendor/Psr/SimpleCache/
        'Psr\\SimpleCache\\' => [
            __DIR__ . '/../../../vendor/Psr/SimpleCache/',
        ],

        // ZipStream：vendor/ZipStream/
        'ZipStream\\' => [
            __DIR__ . '/../../../vendor/ZipStream/',
        ],
    ];

    foreach ($map as $prefix => $baseDirs) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }
        $relative = substr($class, $len);
        $relPath  = str_replace('\\', '/', $relative) . '.php';

        foreach ($baseDirs as $baseDir) {
            $file = $baseDir . $relPath;
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});
