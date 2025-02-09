<?php

declare(strict_types=1);

namespace Tests\App\Models\LionDatabase\MySQL;

use App\Models\LionDatabase\MySQL\AuthenticatorModel;
use App\Models\LionDatabase\MySQL\UsersModel;
use Database\Class\Authenticator2FA;
use Database\Class\LionDatabase\MySQL\Users;
use Database\Factory\LionDatabase\MySQL\UsersFactory;
use Database\Migrations\LionDatabase\MySQL\Tables\DocumentTypes as DocumentTypesTable;
use Database\Migrations\LionDatabase\MySQL\Tables\Roles as RolesTable;
use Database\Migrations\LionDatabase\MySQL\Tables\Users as UsersTable;
use Database\Migrations\LionDatabase\MySQL\Views\ReadUsersById;
use Database\Seed\LionDatabase\MySQL\DocumentTypesSeed;
use Database\Seed\LionDatabase\MySQL\RolesSeed;
use Database\Seed\LionDatabase\MySQL\UsersSeed;
use DI\DependencyException;
use DI\NotFoundException;
use Lion\Bundle\Test\Test;
use Lion\Request\Http;
use Lion\Request\Status;
use PHPUnit\Framework\Attributes\Test as Testing;
use stdClass;
use Tests\Providers\AuthJwtProviderTrait;

class AuthenticatorModelTest extends Test
{
    use AuthJwtProviderTrait;

    private AuthenticatorModel $authenticatorModel;
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
        ]);

        $this->executeSeedsGroup([
            DocumentTypesSeed::class,
            RolesSeed::class,
            UsersSeed::class,
        ]);

        $this->authenticatorModel = new AuthenticatorModel();

        $this->usersModel = new UsersModel();
    }

    #[Testing]
    public function readUsersPasswordDB(): void
    {
        $users = $this->usersModel->readUsersDB();

        $this->assertIsArray($users);
        $this->assertCount(self::AVAILABLE_USERS, $users);

        $user = reset($users);

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('idusers', $user);
        $this->assertIsInt($user->idusers);

        $capsule = new Users()
            ->setIdusers($user->idusers)
            ->setUsersPassword(UsersFactory::USERS_PASSWORD);

        /** @var Users $databaseCapsule */
        $databaseCapsule = $this->authenticatorModel->readUsersPasswordDB($capsule);

        $this->assertIsObject($databaseCapsule);
        $this->assertInstanceOf(Users::class, $databaseCapsule);
        $this->assertIsString($databaseCapsule->getUsersPassword());
    }

    #[Testing]
    public function readCheckStatus(): void
    {
        $users = $this->usersModel->readUsersDB();

        $this->assertIsArray($users);
        $this->assertCount(self::AVAILABLE_USERS, $users);

        $user = reset($users);

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('idusers', $user);
        $this->assertIsInt($user->idusers);

        /** @var stdClass $status */
        $status = $this->authenticatorModel->readCheckStatusDB(
            new Authenticator2FA()
                ->setIdusers($user->idusers)
        );

        $this->assertIsObject($status);
        $this->assertInstanceOf(stdClass::class, $status);
        $this->assertObjectHasProperty('users_2fa', $status);
        $this->assertSame(UsersFactory::DISABLED_2FA, $status->users_2fa);
    }

    #[Testing]
    public function update2FADB(): void
    {
        $users = $this->usersModel->readUsersDB();

        $this->assertIsArray($users);
        $this->assertCount(self::AVAILABLE_USERS, $users);

        $user = reset($users);

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('idusers', $user);
        $this->assertIsInt($user->idusers);

        $capsule = new Authenticator2FA()
            ->setIdusers($user->idusers)
            ->setUsers2fa(UsersFactory::ENABLED_2FA);

        $response = $this->authenticatorModel->update2FADB($capsule);

        $this->assertIsObject($response);
        $this->assertInstanceOf(stdClass::class, $response);
        $this->assertObjectHasProperty('code', $response);
        $this->assertObjectHasProperty('status', $response);
        $this->assertObjectHasProperty('message', $response);
        $this->assertIsInt($response->code);
        $this->assertSame(Http::OK, $response->code);
        $this->assertIsString($response->status);
        $this->assertSame(Status::SUCCESS, $response->status);
        $this->assertIsString($response->message);
        $this->assertSame('execution finished', $response->message);

        /** @var stdClass $response */
        $response = $this->usersModel->readUsersByIdDB(
            new Users()
                ->setIdusers($capsule->getIdusers())
        );

        $this->assertIsObject($response);
        $this->assertInstanceOf(stdClass::class, $response);
        $this->assertObjectHasProperty('users_2fa', $response);
        $this->assertSame($capsule->getUsers2fa(), $response->users_2fa);
    }
}
