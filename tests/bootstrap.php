<?php

declare(strict_types=1);

define('LION_START', microtime(true));

define('IS_INDEX', false);

/**
 * -----------------------------------------------------------------------------
 * Register The Auto Loader
 * -----------------------------------------------------------------------------
 * Composer provides a convenient, automatically generated class loader for
 * this application
 * -----------------------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Lion\Database\Driver;
use Lion\Files\Store;
use Lion\Mailer\Mailer;

/**
 * -----------------------------------------------------------------------------
 * Register environment variable loader automatically
 * -----------------------------------------------------------------------------
 * .dotenv provides an easy way to access environment variables with $_ENV
 * -----------------------------------------------------------------------------
 */

if (isSuccess(new Store()->exist(__DIR__ . '/../.env'))) {
    Dotenv::createMutable(__DIR__ . '/../')->load();
}

/**
 * -----------------------------------------------------------------------------
 * Database initialization
 * -----------------------------------------------------------------------------
 */

Driver::run([
    'default' => env('DB_NAME', 'lion_database'),
    'connections' => [
        env('DB_NAME', 'lion_database') => [
            'type' => env('DB_TYPE', 'mysql'),
            'host' => env('DB_HOST', 'mysql'),
            'port' => env('DB_PORT', 3306),
            'dbname' => env('DB_NAME', 'lion_database'),
            'user' => env('DB_USER', 'root'),
            'password' => env('DB_PASSWORD', 'lion'),
        ],
    ]
]);

/**
 * -----------------------------------------------------------------------------
 * Email initialization
 * -----------------------------------------------------------------------------
 */

Mailer::initialize([
    env('MAIL_NAME', 'lion-framework') => [
        'name' => env('MAIL_NAME', 'lion-framework'),
        'type' => env('MAIL_TYPE', 'phpmailer'),
        'host' => env('MAIL_HOST', 'mailhog'),
        'username' => env('MAIL_USER_NAME', 'lion-framework'),
        'password' => env('MAIL_PASSWORD', 'lion'),
        'port' => (int) env('MAIL_PORT', 1025),
        'encryption' => env('MAIL_ENCRYPTION', false),
        'debug' => env('MAIL_DEBUG', false),
    ],
], env('MAIL_NAME', 'lion-framework'));

/**
 * -----------------------------------------------------------------------------
 * Local zone configuration
 * -----------------------------------------------------------------------------
 */

date_default_timezone_set(env('SERVER_DATE_TIMEZONE', 'America/Bogota'));