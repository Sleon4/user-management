<?php

declare(strict_types=1);

namespace Database\Seed\LionDatabase\MySQL;

use Database\Factory\LionDatabase\MySQL\RolesFactory;
use Lion\Bundle\Interface\SeedInterface;
use Lion\Database\Drivers\MySQL as DB;
use stdClass;

/**
 * Seed for roles
 *
 * @package Database\Seed\LionDatabase\MySQL
 */
class RolesSeed implements SeedInterface
{
    /**
     * [Index number for seed execution priority]
     *
     * @const INDEX
     */
    public const ?int INDEX = 2;

    /**
     * {@inheritdoc}
     **/
    public function run(): stdClass
    {
        return DB::table('roles')
            ->bulk(RolesFactory::columns(), RolesFactory::definition())
            ->execute();
    }
}
