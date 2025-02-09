<?php

declare(strict_types=1);

define('LION_START', microtime(true));

define('IS_INDEX', true);

/**
 * -----------------------------------------------------------------------------
 * Register The Auto Loader
 * -----------------------------------------------------------------------------
 * Composer provides a convenient, automatically generated class loader for this
 * application
 * -----------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../vendor/autoload.php');

use App\Http\Controllers\LionDatabase\MySQL\AuthenticatorController;
use App\Http\Controllers\LionDatabase\MySQL\LoginController;
use App\Http\Controllers\LionDatabase\MySQL\PasswordManagerController;
use App\Http\Controllers\LionDatabase\MySQL\ProfileController;
use App\Http\Controllers\LionDatabase\MySQL\RegistrationController;
use App\Http\Controllers\LionDatabase\MySQL\UsersController;
use Dotenv\Dotenv;
use Lion\Bundle\Helpers\Http\Routes;
use Lion\Database\Driver;
use Lion\Exceptions\Serialize;
use Lion\Files\Store;
use Lion\Mailer\Mailer;
use Lion\Request\Http;
use Lion\Request\Request;
use Lion\Route\Route;

/**
 * -----------------------------------------------------------------------------
 * Initialize exception handling
 * -----------------------------------------------------------------------------
 * Controls and serializes exceptions to JSON format
 * -----------------------------------------------------------------------------
 */

new Serialize()
    ->exceptionHandler();

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
 * Cross-Origin Resource Sharing (CORS) Configuration
 * -----------------------------------------------------------------------------
 * Here you can configure your settings for cross-origin resource
 * sharing or "CORS". This determines which cross-origin operations
 * can be executed in web browsers.
 * -----------------------------------------------------------------------------
 */

Request::header('Access-Control-Allow-Origin', env('SERVER_URL_AUD', 'http://localhost:5173'));

Request::header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');

Request::header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');

Request::header('Access-Control-Max-Age', '3600');

if (Http::OPTIONS === $_SERVER['REQUEST_METHOD']) {
    http_response_code(Http::OK);

    exit(0);
}

Request::header('Content-Type', 'application/json; charset=UTF-8');

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

/**
 * -----------------------------------------------------------------------------
 * Web Routes
 * -----------------------------------------------------------------------------
 * Here is where you can register web routes for your application
 * -----------------------------------------------------------------------------
 */

Route::init();

Route::addMiddleware(Routes::getMiddleware());

Route::middleware(['https'], function (): void {
    Route::prefix('api', function (): void {
        Route::prefix('auth', function (): void {
            Route::post('login', [LoginController::class, 'auth']);
            Route::post('2fa', [LoginController::class, 'auth2FA']);
            Route::post('register', [RegistrationController::class, 'register']);
            Route::post('verify', [RegistrationController::class, 'verifyAccount']);
            Route::post('refresh', [LoginController::class, 'refresh'], ['jwt-existence']);

            Route::prefix('recovery', function (): void {
                Route::post('password', [PasswordManagerController::class, 'recoveryPassword']);
                Route::post('verify-code', [PasswordManagerController::class, 'updateLostPassword']);
            });
        });

        Route::middleware(['jwt-authorize'], function (): void {
            Route::prefix('profile', function (): void {
                Route::get('/', [ProfileController::class, 'readProfile']);
                Route::put('/', [ProfileController::class, 'updateProfile']);
                Route::post('password', [PasswordManagerController::class, 'updatePassword']);

                Route::controller(AuthenticatorController::class, function (): void {
                    Route::post('2fa/verify', 'passwordVerify');
                    Route::get('2fa/qr', 'qr');
                    Route::post('2fa/enable', 'enable2FA');
                    Route::post('2fa/disable', 'disable2FA');
                });
            });

            Route::middleware(['admin-access'], function (): void {
                Route::controller(UsersController::class, function (): void {
                    Route::post('users', 'createUsers');
                    Route::get('users', 'readUsers');
                    Route::get('users/{idusers:i}', 'readUsersById');
                    Route::put('users/{idusers:i}', 'updateUsers');
                    Route::delete('users/{idusers:i}', 'deleteUsers');
                });
            });
        });
    });
});

Route::dispatch();