<?php

use App\Kernel;

if (!is_file(dirname(__DIR__).'/.env')) {
    $_SERVER['APP_RUNTIME_OPTIONS']['disable_dotenv'] = true;
    $_ENV['APP_RUNTIME_OPTIONS']['disable_dotenv'] = true;
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    date_default_timezone_set($context['APP_TIMEZONE'] ?? 'UTC');

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
