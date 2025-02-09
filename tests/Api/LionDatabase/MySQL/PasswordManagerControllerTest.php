<?php

declare(strict_types=1);

namespace Tests\Api\LionDatabase\MySQL;

use App\Models\LionDatabase\MySQL\UsersModel;
use Database\Class\LionDatabase\MySQL\Users;
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
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Lion\Bundle\Helpers\Http\Fetch;
use Lion\Bundle\Helpers\Http\FetchConfiguration;
use Lion\Bundle\Test\Test;
use Lion\Request\Http;
use Lion\Request\Status;
use PHPUnit\Framework\Attributes\Test as Testing;
use stdClass;
use Tests\Providers\AuthJwtProviderTrait;

class PasswordManagerControllerTest extends Test
{
    use AuthJwtProviderTrait;

    private const string USERS_EMAIL = 'root@dev.com';
    private const string USERS_PASSWORD = 'lion-password';

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
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function recoveryPassword(): void
    {
        $response = fetch(
            new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/password'), [
                'json' => [
                    'users_email' => self::USERS_EMAIL,
                ],
            ])
                ->setFetchConfiguration(
                    new FetchConfiguration([
                        'verify' => false,
                    ])
                )
        )
            ->getBody()
            ->getContents();

        $this->assertJsonContent($response, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'confirmation code sent, check your email inbox to see your verification code',
        ]);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    #[Testing]
    public function recoveryPasswordCodeNotNull(): void
    {
        $response = fetch(
            new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/password'), [
                'json' => [
                    'users_email' => self::USERS_EMAIL,
                ],
            ])
                ->setFetchConfiguration(
                    new FetchConfiguration([
                        'verify' => false,
                    ])
                )
        )
            ->getBody()
            ->getContents();

        $this->assertJsonContent($response, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'confirmation code sent, check your email inbox to see your verification code',
        ]);

        $exception = $this->getExceptionFromApi(function (): void {
            fetch(
                new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/password'), [
                    'json' => [
                        'users_email' => self::USERS_EMAIL,
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            );
        });

        $this->assertJsonContent($this->getResponse($exception->getMessage(), 'response:'), [
            'code' => Http::FORBIDDEN,
            'status' => Status::ERROR,
            'message' => 'a verification code has already been sent to this account',
        ]);
    }

    /**
     * @throws Exception
     */
    #[Testing]
    public function recoveryPasswordIncorrect1(): void
    {
        $exception = $this->getExceptionFromApi(function (): void {
            fetch(
                new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/password'), [
                    'json' => [
                        'users_email' => fake()->email(),
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            );
        });

        $this->assertJsonContent($this->getResponse($exception->getMessage(), 'response:'), [
            'code' => Http::UNAUTHORIZED,
            'status' => Status::SESSION_ERROR,
            'message' => 'email/password is incorrect [AUTH-1]',
        ]);
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function updateLostPassword(): void
    {
        $response = fetch(
            new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/password'), [
                'json' => [
                    'users_email' => self::USERS_EMAIL,
                ],
            ])
                ->setFetchConfiguration(
                    new FetchConfiguration([
                        'verify' => false,
                    ])
                )
        )
            ->getBody()
            ->getContents();

        $this->assertJsonContent($response, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'confirmation code sent, check your email inbox to see your verification code',
        ]);

        /** @var stdClass $user */
        $user = new UsersModel()->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(self::USERS_EMAIL)
        );

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('users_recovery_code', $user);
        $this->assertIsString($user->users_recovery_code);

        $encode = $this->AESEncode([
            'users_password_new' => self::USERS_PASSWORD,
            'users_password_confirm' => self::USERS_PASSWORD,
        ]);

        $response = fetch(
            new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/verify-code'), [
                'json' => [
                    'users_email' => self::USERS_EMAIL,
                    'users_password_new' => $encode['users_password_new'],
                    'users_password_confirm' => $encode['users_password_confirm'],
                    'users_recovery_code' => $user->users_recovery_code,
                ],
            ])
                ->setFetchConfiguration(
                    new FetchConfiguration([
                        'verify' => false,
                    ])
                )
        )
            ->getBody()
            ->getContents();

        $this->assertJsonContent($response, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'the recovery code is valid, your password has been updated successfully',
        ]);

        /** @var stdClass $user */
        $user = new UsersModel()->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(self::USERS_EMAIL)
        );

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('users_recovery_code', $user);
        $this->assertNull($user->users_recovery_code);
    }

    /**
     * @throws Exception
     */
    #[Testing]
    public function updateLostPasswordIncorrect1(): void
    {
        $exception = $this->getExceptionFromApi(function (): void {
            $encode = $this->AESEncode([
                'users_password_new' => self::USERS_PASSWORD,
                'users_password_confirm' => self::USERS_PASSWORD,
            ]);

            fetch(
                new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/verify-code'), [
                    'json' => [
                        'users_email' => fake()->email(),
                        'users_password_new' => $encode['users_password_new'],
                        'users_password_confirm' => $encode['users_password_confirm'],
                        'users_recovery_code' => fake()->numerify('######'),
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            );
        });

        $this->assertJsonContent($this->getResponse($exception->getMessage(), 'response:'), [
            'code' => Http::UNAUTHORIZED,
            'status' => Status::SESSION_ERROR,
            'message' => 'email/password is incorrect [AUTH-1]',
        ]);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    #[Testing]
    public function updateLostPasswordInvalid1(): void
    {
        $response = fetch(
            new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/password'), [
                'json' => [
                    'users_email' => self::USERS_EMAIL,
                ],
            ])
                ->setFetchConfiguration(
                    new FetchConfiguration([
                        'verify' => false,
                    ])
                )
        )
            ->getBody()
            ->getContents();

        $this->assertJsonContent($response, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'confirmation code sent, check your email inbox to see your verification code',
        ]);

        /** @var stdClass $user */
        $user = new UsersModel()->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(self::USERS_EMAIL)
        );

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('users_recovery_code', $user);
        $this->assertIsString($user->users_recovery_code);

        $exception = $this->getExceptionFromApi(function (): void {
            $encode = $this->AESEncode([
                'users_password_new' => self::USERS_PASSWORD,
                'users_password_confirm' => self::USERS_PASSWORD,
            ]);

            fetch(
                new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/verify-code'), [
                    'json' => [
                        'users_email' => self::USERS_EMAIL,
                        'users_password_new' => $encode['users_password_new'],
                        'users_password_confirm' => $encode['users_password_confirm'],
                        'users_recovery_code' => fake()->numerify('######'),
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            );
        });

        $this->assertJsonContent($this->getResponse($exception->getMessage(), 'response:'), [
            'code' => Http::FORBIDDEN,
            'status' => Status::ERROR,
            'message' => 'verification code is invalid [ERR-2]',
        ]);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    #[Testing]
    public function updateLostPasswordIncorrect2(): void
    {
        $response = fetch(
            new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/password'), [
                'json' => [
                    'users_email' => self::USERS_EMAIL,
                ],
            ])
                ->setFetchConfiguration(
                    new FetchConfiguration([
                        'verify' => false,
                    ])
                )
        )
            ->getBody()
            ->getContents();

        $this->assertJsonContent($response, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'confirmation code sent, check your email inbox to see your verification code',
        ]);

        /** @var stdClass $user */
        $user = new UsersModel()->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(self::USERS_EMAIL)
        );

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('users_recovery_code', $user);
        $this->assertIsString($user->users_recovery_code);

        $exception = $this->getExceptionFromApi(function () use ($user) {
            $encode = $this->AESEncode([
                'users_password_new' => UsersFactory::USERS_PASSWORD,
                'users_password_confirm' => self::USERS_PASSWORD,
            ]);

            fetch(
                new Fetch(Http::POST, (env('SERVER_URL') . '/api/auth/recovery/verify-code'), [
                    'json' => [
                        'users_email' => self::USERS_EMAIL,
                        'users_password_new' => $encode['users_password_new'],
                        'users_password_confirm' => $encode['users_password_confirm'],
                        'users_recovery_code' => $user->users_recovery_code,
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            );
        });

        $this->assertJsonContent($this->getResponse($exception->getMessage(), 'response:'), [
            'code' => Http::UNAUTHORIZED,
            'status' => Status::ERROR,
            'message' => 'password is incorrect [ERR-2]',
        ]);
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function updatePassword(): void
    {
        /** @var stdClass $user */
        $user = new UsersModel()->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(self::USERS_EMAIL)
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

        $response = fetch(
            new Fetch(Http::POST, (env('SERVER_URL') . '/api/profile/password'), [
                'headers' => [
                    'Authorization' => $this->getAuthorization([
                        'idusers' => $encode['idusers'],
                    ])
                ],
                'json' => [
                    'users_password' => $encode['users_password'],
                    'users_password_new' => $encode['users_password_new'],
                    'users_password_confirm' => $encode['users_password_confirm'],
                ],
            ])
                ->setFetchConfiguration(
                    new FetchConfiguration([
                        'verify' => false,
                    ])
                )
        )
            ->getBody()
            ->getContents();

        $this->assertJsonContent($response, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'password updated successfully',
        ]);
    }

    /**
     * @throws Exception
     */
    #[Testing]
    public function updatePasswordIncorrect1(): void
    {
        /** @var stdClass $user */
        $user = new UsersModel()->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(self::USERS_EMAIL)
        );

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('idusers', $user);
        $this->assertIsInt($user->idusers);

        $exception = $this->getExceptionFromApi(function () use ($user): void {
            $encode = $this->AESEncode([
                'idusers' => (string) $user->idusers,
                'users_password' => self::USERS_PASSWORD,
                'users_password_new' => self::USERS_PASSWORD,
                'users_password_confirm' => self::USERS_PASSWORD,
            ]);

            fetch(
                new Fetch(Http::POST, (env('SERVER_URL') . '/api/profile/password'), [
                    'headers' => [
                        'Authorization' => $this->getAuthorization([
                            'idusers' => $encode['idusers'],
                        ]),
                    ],
                    'json' => [
                        'users_password' => $encode['users_password'],
                        'users_password_new' => $encode['users_password_new'],
                        'users_password_confirm' => $encode['users_password_confirm'],
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            );
        });

        $this->assertJsonContent($this->getResponse($exception->getMessage(), 'response:'), [
            'code' => Http::UNAUTHORIZED,
            'status' => Status::ERROR,
            'message' => 'password is incorrect [ERR-1]',
        ]);
    }

    /**
     * @throws Exception
     */
    #[Testing]
    public function updatePasswordIncorrect2(): void
    {
        /** @var stdClass $user */
        $user = new UsersModel()->readUsersByEmailDB(
            new Users()
                ->setUsersEmail(self::USERS_EMAIL)
        );

        $this->assertIsObject($user);
        $this->assertInstanceOf(stdClass::class, $user);
        $this->assertObjectHasProperty('idusers', $user);
        $this->assertIsInt($user->idusers);

        $exception = $this->getExceptionFromApi(function () use ($user) {
            $encode = $this->AESEncode([
                'idusers' => (string) $user->idusers,
                'users_password' => UsersFactory::USERS_PASSWORD,
                'users_password_new' => UsersFactory::USERS_PASSWORD,
                'users_password_confirm' => self::USERS_PASSWORD,
            ]);

            fetch(
                new Fetch(Http::POST, (env('SERVER_URL') . '/api/profile/password'), [
                    'headers' => [
                        'Authorization' => $this->getAuthorization([
                            'idusers' => $encode['idusers'],
                        ]),
                    ],
                    'json' => [
                        'users_password' => $encode['users_password'],
                        'users_password_new' => $encode['users_password_new'],
                        'users_password_confirm' => $encode['users_password_confirm'],
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            );
        });

        $this->assertJsonContent($this->getResponse($exception->getMessage(), 'response:'), [
            'code' => Http::UNAUTHORIZED,
            'status' => Status::ERROR,
            'message' => 'password is incorrect [ERR-2]',
        ]);
    }
}
