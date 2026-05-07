<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'test') {
    $testDatabasePath = str_replace('\\', '/', dirname(__DIR__).'/var/test.db');
    $testDatabaseUrl = 'sqlite:///'.$testDatabasePath;

    $_ENV['DATABASE_URL'] = $testDatabaseUrl;
    $_SERVER['DATABASE_URL'] = $testDatabaseUrl;
    putenv('DATABASE_URL='.$testDatabaseUrl);
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
