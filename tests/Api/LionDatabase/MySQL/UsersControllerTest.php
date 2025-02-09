<?php

declare(strict_types=1);

namespace Tests\Api\LionDatabase\MySQL;

use App\Enums\DocumentTypesEnum;
use App\Enums\RolesEnum;
use Database\Factory\LionDatabase\MySQL\UsersFactory;
use Database\Migrations\LionDatabase\MySQL\StoreProcedures\CreateUsers;
use Database\Migrations\LionDatabase\MySQL\StoreProcedures\DeleteUsers;
use Database\Migrations\LionDatabase\MySQL\StoreProcedures\UpdateUsers;
use Database\Migrations\LionDatabase\MySQL\Tables\DocumentTypes as DocumentTypesTable;
use Database\Migrations\LionDatabase\MySQL\Tables\Roles as RolesTable;
use Database\Migrations\LionDatabase\MySQL\Tables\Users as UsersTable;
use Database\Migrations\LionDatabase\MySQL\Views\ReadUsers;
use Database\Migrations\LionDatabase\MySQL\Views\ReadUsersById;
use Database\Seed\LionDatabase\MySQL\DocumentTypesSeed;
use Database\Seed\LionDatabase\MySQL\RolesSeed;
use Database\Seed\LionDatabase\MySQL\UsersSeed;
use DI\DependencyException;
use DI\NotFoundException;
use GuzzleHttp\Exception\GuzzleException;
use Lion\Bundle\Helpers\Http\Fetch;
use Lion\Bundle\Helpers\Http\FetchConfiguration;
use Lion\Bundle\Test\Test;
use Lion\Database\Drivers\Schema\MySQL as Schema;
use Lion\Request\Http;
use Lion\Request\Status;
use PHPUnit\Framework\Attributes\Test as Testing;
use Tests\Providers\AuthJwtProviderTrait;

class UsersControllerTest extends Test
{
    use AuthJwtProviderTrait;

    private const array JSON_UPDATE_USERS = [
        'idroles' => RolesEnum::ADMINISTRATOR->value,
        'iddocument_types' => DocumentTypesEnum::PASSPORT->value,
        'users_citizen_identification' => '##########',
        'users_name' => 'Sergio',
        'users_last_name' => 'Leon',
        'users_nickname' => 'Sleon',
        'users_email' => 'sleon@dev.com',
    ];

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
            ReadUsers::class,
            ReadUsersById::class,
            CreateUsers::class,
            UpdateUsers::class,
            DeleteUsers::class,
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
    public function createUsers(): void
    {
        $encode = $this->AESEncode([
            'idroles' => (string) RolesEnum::ADMINISTRATOR->value,
        ]);

        $response = fetch(
            new Fetch(Http::POST, env('SERVER_URL') . '/api/users', [
                'headers' => [
                    'Authorization' => $this->getAuthorization([
                        'idroles' => $encode['idroles'],
                    ]),
                ],
                'json' => [
                    'idroles' => RolesEnum::ADMINISTRATOR->value,
                    'iddocument_types' => DocumentTypesEnum::PASSPORT->value,
                    'users_citizen_identification' => fake()->numerify('##########'),
                    'users_name' => fake()->name(),
                    'users_last_name' => fake()->lastName(),
                    'users_nickname' => fake()->userName(),
                    'users_email' => fake()->email(),
                    'users_password' => UsersFactory::USERS_PASSWORD_HASH,
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
            'message' => 'registered user successfully',
        ]);
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function readUsers(): void
    {
        $encode = $this->AESEncode([
            'idroles' => (string) RolesEnum::ADMINISTRATOR->value,
        ]);

        $users = json_decode(
            fetch(
                new Fetch(Http::GET, env('SERVER_URL') . '/api/users', [
                    'headers' => [
                        'Authorization' => $this->getAuthorization([
                            'idroles' => $encode['idroles'],
                        ]),
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            )
                ->getBody()
                ->getContents(),
            true
        );

        $this->assertIsArray($users);
        $this->assertCount(self::AVAILABLE_USERS, $users);
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function readUsersWithoutData(): void
    {
        Schema::truncateTable('users')->execute();

        $encode = $this->AESEncode([
            'idroles' => (string) RolesEnum::ADMINISTRATOR->value,
        ]);

        $users = fetch(
            new Fetch(Http::GET, env('SERVER_URL') . '/api/users', [
                'headers' => [
                    'Authorization' => $this->getAuthorization([
                        'idroles' => $encode['idroles'],
                    ]),
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

        $this->assertJsonContent($users, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'no data available',
        ]);
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function readUsersById(): void
    {
        $encode = $this->AESEncode([
            'idroles' => (string) RolesEnum::ADMINISTRATOR->value,
        ]);

        $users = json_decode(
            fetch(
                new Fetch(Http::GET, env('SERVER_URL') . '/api/users', [
                    'headers' => [
                        'Authorization' => $this->getAuthorization([
                            'idroles' => $encode['idroles'],
                        ]),
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            )
                ->getBody()
                ->getContents(),
            true
        );

        $this->assertIsArray($users);
        $this->assertCount(self::AVAILABLE_USERS, $users);

        $firstUser = reset($users);

        $this->assertIsArray($firstUser);
        $this->assertArrayHasKey('idusers', $firstUser);
        $this->assertIsInt($firstUser['idusers']);

        $user = json_decode(
            fetch(
                new Fetch(Http::GET, (env('SERVER_URL') . '/api/users/' . $firstUser['idusers']), [
                    'headers' => [
                        'Authorization' => $this->getAuthorization([
                            'idroles' => $encode['idroles'],
                        ]),
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            )
                ->getBody()
                ->getContents(),
            true
        );

        $this->assertIsArray($user);
        $this->assertIsString($user['users_name']);
        $this->assertIsString($user['users_last_name']);
        $this->assertSame('root', $user['users_name']);
        $this->assertSame('lion', $user['users_last_name']);
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function readUsersByIdWithoutData(): void
    {
        Schema::truncateTable('users')->execute();

        $encode = $this->AESEncode([
            'idroles' => (string) RolesEnum::ADMINISTRATOR->value,
        ]);

        $users = fetch(
            new Fetch(Http::GET, (env('SERVER_URL') . '/api/users/1'), [
                'headers' => [
                    'Authorization' => $this->getAuthorization([
                        'idroles' => $encode['idroles'],
                    ]),
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

        $this->assertJsonContent($users, [
            'code' => Http::OK,
            'status' => Status::SUCCESS,
            'message' => 'no data available',
        ]);
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function updateUsers(): void
    {
        $encode = $this->AESEncode([
            'idroles' => (string) RolesEnum::ADMINISTRATOR->value,
        ]);

        $token = $this->getAuthorization([
            'idroles' => $encode['idroles'],
        ]);

        $users = json_decode(
            fetch(
                new Fetch(Http::GET, env('SERVER_URL') . '/api/users', [
                    'headers' => [
                        'Authorization' => $token,
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            )
                ->getBody()
                ->getContents(),
            true
        );

        $this->assertIsArray($users);
        $this->assertCount(self::AVAILABLE_USERS, $users);

        $firstUser = reset($users);

        $this->assertIsArray($firstUser);
        $this->assertArrayHasKey('idusers', $firstUser);
        $this->assertIsInt($firstUser['idusers']);

        $response = fetch(
            new Fetch(Http::PUT, env('SERVER_URL') . '/api/users/' . $firstUser['idusers'], [
                'json' => self::JSON_UPDATE_USERS,
                'headers' => [
                    'Authorization' => $token,
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
            'message' => 'the registered user has been successfully updated',
        ]);

        $users = json_decode(
            fetch(
                new Fetch(Http::GET, env('SERVER_URL') . '/api/users', [
                    'headers' => [
                        'Authorization' => $token,
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            )
                ->getBody()
                ->getContents(),
            true
        );

        $this->assertIsArray($users);
        $this->assertCount(self::AVAILABLE_USERS, $users);

        $firstUser = reset($users);

        $this->assertIsArray($firstUser);
        $this->assertArrayHasKey('users_name', $firstUser);
        $this->assertArrayHasKey('users_last_name', $firstUser);
        $this->assertIsString($firstUser['users_name']);
        $this->assertIsString($firstUser['users_last_name']);
        $this->assertSame(self::JSON_UPDATE_USERS['users_name'], $firstUser['users_name']);
        $this->assertSame(self::JSON_UPDATE_USERS['users_last_name'], $firstUser['users_last_name']);
    }

    /**
     * @throws GuzzleException
     */
    #[Testing]
    public function deleteUsers(): void
    {
        $encode = $this->AESEncode([
            'idroles' => (string) RolesEnum::ADMINISTRATOR->value,
        ]);

        $token = $this->getAuthorization([
            'idroles' => $encode['idroles'],
        ]);

        $users = json_decode(
            fetch(
                new Fetch(Http::GET, env('SERVER_URL') . '/api/users', [
                    'headers' => [
                        'Authorization' => $token,
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            )
                ->getBody()
                ->getContents(),
            true
        );

        $this->assertIsArray($users);
        $this->assertCount(self::AVAILABLE_USERS, $users);

        $firstUser = reset($users);

        $this->assertIsArray($firstUser);
        $this->assertArrayHasKey('idusers', $firstUser);
        $this->assertIsInt($firstUser['idusers']);

        $response = fetch(
            new Fetch(Http::DELETE, env('SERVER_URL') . '/api/users/' . $firstUser['idusers'], [
                'headers' => [
                    'Authorization' => $token,
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
            'message' => 'the registered user has been successfully deleted',
        ]);

        $users = json_decode(
            fetch(
                new Fetch(Http::GET, env('SERVER_URL') . '/api/users', [
                    'headers' => [
                        'Authorization' => $token,
                    ],
                ])
                    ->setFetchConfiguration(
                        new FetchConfiguration([
                            'verify' => false,
                        ])
                    )
            )
                ->getBody()
                ->getContents()
        );

        $this->assertIsArray($users);
        $this->assertCount(self::REMAINING_USERS, $users);
    }
}
