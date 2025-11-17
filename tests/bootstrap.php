<?php

/**
 * PHPUnit bootstrap file for CustomerChangeNotify plugin
 */

// テストのための基本的なセットアップ
spl_autoload_register(function ($class): void {
    $prefixes = [
        'Plugin\\CustomerChangeNotify\\' => __DIR__ . '/../',
        'Doctrine\\' => __DIR__ . '/Fixtures/Doctrine/',
        'Twig_' => __DIR__ . '/Fixtures/Twig/',
        'Swift_' => __DIR__ . '/Fixtures/Swift/',
        'Eccube\\' => __DIR__ . '/Fixtures/Eccube/',
        'Psr\\Log\\' => __DIR__ . '/Fixtures/Psr/Log/',
        'Symfony\\' => __DIR__ . '/Fixtures/Symfony/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relative);

        // Twig_ 形式はプレフィックスを含めたファイル名となる
        if ($prefix === 'Twig_') {
            $file = $baseDir . $class . '.php';
        } elseif ($prefix === 'Swift_') {
            $file = $baseDir . str_replace('_', '/', $class) . '.php';
        } else {
            $file = $baseDir . $relativePath . '.php';
        }

        if (file_exists($file)) {
            require_once $file;
        }
    }
});
