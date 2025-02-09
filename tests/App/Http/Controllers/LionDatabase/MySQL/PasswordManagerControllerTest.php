<?php

declare(strict_types=1);

namespace Tests\App\Http\Controllers\LionDatabase\MySQL;

use App\Exceptions\AccountException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\PasswordException;
use App\Http\Controllers\LionDatabase\MySQL\PasswordManagerController;
use App\Http\Services\AESService;
use App\Http\Services\JWTService;
use App\Http\Services\LionDatabase\MySQL\AccountService;
use App\Http\Services\LionDatabase\MySQL\LoginService;
use App\Http\Services\LionDatabase\MySQL\PasswordManagerService;
use App\Models\LionDatabase\MySQL\LoginModel;
use App\Models\LionDatabase\MySQL\PasswordManagerModel;
use App\Models\LionDatabase\MySQL\UsersModel;
use Database\Class\LionDatabase\MySQL\Users;
use Database\Class\PasswordManager;
use Database\Factory\LionDatabase\MySQL\UsersFactory;
use Database\Migrations\LionDatabase\MySQL\StoreProcedures\UpdatePassword;
use Database\Migrations\LionDatabase\MySQL\StoreProcedures\UpdateRecoveryCode;
use Database\Migrations\LionDatabase\MySQL\Tables\DocumentTypes as DocumentTypesTable;
use Database\Migrations\LionDatabase\MySQL\Tables\Roles as RolesTable;
use Database\Migrations\LionDatabase\MySQL\Tables\Users as UsersTable;
use Database\Migrations\LionDatabase\MySQL\Views\ReadUsersById;
use Database\Seed\LionDatabase\MySQL\DocumentTypesSeed;
use Database\Seed\LionDatabase\MySQL\RolesSeed;
use Database\Seed\LionDatabase\MySQL\UsersSeed;
use DI\DependencyException;
use DI\NotFoundException;
use Lion\Bundle\Helpers\Commands\Schedule\TaskQueue;
use Lion\Bundle\Helpers\Redis;
use Lion\Bundle\Test\Test;
use Lion\Request\Http;
use Lion\Request\Status;
use Lion\Security\AES;
use Lion\Security\Exceptions\AESException;
use Lion\Security\JWT;
use Lion\Security\RSA;
use Lion\Security\Validation;
use PHPUnit\Framework\Attributes\Test as Testing;
use stdClass;
use Tests\Providers\AuthJwtProviderTrait;

class PasswordManagerControllerTest extends Test
{
    use AuthJwtProviderTrait;

    private const string USERS_PASSWORD = 'lion-password';

    private PasswordManagerController $passwordManagerController;
    private UsersModel $usersModel;

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function setUp(): void
    {
        $this->executeMigrationsGroup([
            DocumentTypesTable::class,
            RolesTable::class,
            UsersTable::class,
            ReadUsersById::class,
            UpdatePassword::class,
            UpdateRecoveryCode::class,
        ]);

        $this->executeSeedsGroup([
            DocumentTypesSeed::class,
            RolesSeed::class,
            UsersSeed::class,
        ]);

        $this->passwordManagerController = new PasswordManagerController();

        $this->usersModel = new UsersModel();
    }

    /**
     * @throws AccountException
     * @throws AuthenticationException
     */
    #[Testing]
    public function recoveryPassword(): void
    {
        $_POST['users_email'] = UsersFactory::USERS_EMAIL;

        $response = $this->passwordManagerController->recoveryPassword(
            new Users(),
            new UsersModel(),
            new AccountService()
                ->setUsersModel(new UsersModel()),
            new LoginService()
                ->setRSA(new RSA())
                ->setJWT(new JWT())
                ->setLoginModel(new LoginModel())
                ->setAESService(
                    new AESService()
                        ->setAES(new AES())
                )
                ->setJWTService(
                    new JWTService()
                        ->setRSA(new RSA())
                        ->setJWT(new JWT())
                ),
            new TaskQueue()
                ->setRedis(new Redis())
        );

        $this->assertIsObject($response);
        $this->assertInstanceOf(stdClass::class, $response);
        $this->assertObjectHasProperty('code', $response);
        $this->assertObjectHasProperty('status', $response);
        $this->assertObjectHasProperty('message', $response);
        $this->assertIsInt($response->code);
        $this->assertIsString($response->status);
        $this->assertIsString($response->message);
        $this->assertSame(Http::OK, $response->code);
        $this->assertSame(Status::SUCCESS, $response->status);

        $this->assertSame(
            'confirmation code sent, check your email inbox to see your verification code',
            $response->message
        );

        $this->assertHttpBodyNotHasKey('users_email');
    }

    /**
     * @throws PasswordException
     * @throws AuthenticationException
     * @throws AccountException
     * @throws AESException
     */
    #[Testing]
    public function updateLostPassword(): void
    {
        $_POST['users_email'] = UsersFactory::USERS_EMAIL;

        $response = $this->passwordManagerController->recoveryPassword(
            new Users(),
            new UsersModel(),
            new AccountService()
                ->setUsersModel(new UsersModel()),
            new LoginService()
                ->setRSA(new RSA())
                ->setJWT(new JWT())
                ->setLoginModel(new LoginModel())
                ->setAESService(
                    new AESService()
                        ->setAES(new AES())
                )
                ->setJWTService(
                    new JWTService()
                        ->setRSA(new RSA())
                        ->setJWT(new JWT())
                ),
            new TaskQueue()
                ->setRedis(new Redis())
        );

        $this->assertIsObject($response);
        $this->assertInstanceOf(stdClass::class, $response);
        $this->assertObjectHasProperty('code', $response);
        $this->assertObjectHasProperty('status', $response);
        $this->assertObjectHasProperty('message', $response);
        $this->assertIsInt($response->code);
        $this->assertIsString($response->status);
        $this->assertIsString($response->message);
        $this->assertSame(Http::OK, $response->code);
        $this->assertSame(Status::SUCCESS, $response->status);

        $this->assertSame(
            'confirmation code sent, check your email inbox to see your verification code',
            $response->message
        );

        /** @var stdClass $user */
        $user = $this->usersModel->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(UsersFactory::USERS_EMAIL)
        );

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('users_recovery_code', $user);
        $this->assertIsString($user->users_recovery_code);

        $encode = $this->AESEncode([
            'users_password_new' => self::USERS_PASSWORD,
            'users_password_confirm' => self::USERS_PASSWORD,
        ]);

        $_POST['users_password_new'] = $encode['users_password_new'];

        $_POST['users_password_confirm'] = $encode['users_password_confirm'];

        $_POST['users_recovery_code'] = $user->users_recovery_code;

        $response = $this->passwordManagerController->updateLostPassword(
            new Users(),
            new PasswordManager(),
            new UsersModel(),
            new PasswordManagerModel(),
            new AccountService()
                ->setUsersModel(new UsersModel()),
            new PasswordManagerService()
                ->setValidation(new Validation()),
            new LoginService()
                ->setRSA(new RSA())
                ->setJWT(new JWT())
                ->setLoginModel(new LoginModel())
                ->setAESService(
                    new AESService()
                        ->setAES(new AES())
                )
                ->setJWTService(
                    new JWTService()
                        ->setRSA(new RSA())
                        ->setJWT(new JWT())
                ),
            new AESService()
                ->setAES(new AES())
        );

        $this->assertIsObject($response);
        $this->assertInstanceOf(stdClass::class, $response);
        $this->assertObjectHasProperty('code', $response);
        $this->assertObjectHasProperty('status', $response);
        $this->assertObjectHasProperty('message', $response);
        $this->assertIsInt($response->code);
        $this->assertIsString($response->status);
        $this->assertIsString($response->message);
        $this->assertSame(Http::OK, $response->code);
        $this->assertSame(Status::SUCCESS, $response->status);

        $this->assertSame(
            'the recovery code is valid, your password has been updated successfully',
            $response->message
        );

        $this->assertHttpBodyNotHasKey('users_email');
        $this->assertHttpBodyNotHasKey('users_password_new');
        $this->assertHttpBodyNotHasKey('users_password_confirm');
        $this->assertHttpBodyNotHasKey('users_recovery_code');
    }

    /**
     * @throws PasswordException
     * @throws AESException
     */
    #[Testing]
    public function updatePassword(): void
    {
        /** @var stdClass $user */
        $user = $this->usersModel->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(UsersFactory::USERS_EMAIL)
        );

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('idusers', $user);
        $this->assertIsInt($user->idusers);

        $encode = $this->AESEncode([
            'idusers' => (string) $user->idusers,
            'users_password' => UsersFactory::USERS_PASSWORD,
            'users_password_new' => self::USERS_PASSWORD,
            'users_password_confirm' => self::USERS_PASSWORD,
        ]);

        $_POST['users_password'] = $encode['users_password'];

        $_POST['users_password_new'] = $encode['users_password_new'];

        $_POST['users_password_confirm'] = $encode['users_password_confirm'];

        $_SERVER['HTTP_AUTHORIZATION'] = $this->getAuthorization([
            'idusers' => $encode['idusers'],
        ]);

        $response = $this->passwordManagerController->updatePassword(
            new PasswordManager(),
            new PasswordManagerModel(),
            new PasswordManagerService()
                ->setValidation(new Validation()),
            new JWTService()
                ->setRSA(new RSA())
                ->setJWT(new JWT()),
            new AESService()
                ->setAES(new AES())
        );

        $this->assertIsObject($response);
        $this->assertInstanceOf(stdClass::class, $response);
        $this->assertObjectHasProperty('code', $response);
        $this->assertObjectHasProperty('status', $response);
        $this->assertObjectHasProperty('message', $response);
        $this->assertIsInt($response->code);
        $this->assertIsString($response->status);
        $this->assertIsString($response->message);
        $this->assertSame(Http::OK, $response->code);
        $this->assertSame(Status::SUCCESS, $response->status);
        $this->assertSame('password updated successfully', $response->message);
        $this->assertHeaderNotHasKey('HTTP_AUTHORIZATION');
        $this->assertHttpBodyNotHasKey('users_password');
        $this->assertHttpBodyNotHasKey('users_password_new');
        $this->assertHttpBodyNotHasKey('users_password_confirm');
    }
}
