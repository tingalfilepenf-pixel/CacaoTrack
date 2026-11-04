<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions, string $role = Role::STAFF): User
    {
        return User::factory()->create([
            'role' => $role,
            'permissions' => array_values(array_unique($permissions)),
        ]);
    }

    /**
     * Refuse to run a database test unless it is pointed at the dedicated
     * MySQL test database. This guard runs before RefreshDatabase can touch a
     * database, so a misconfigured environment can never wipe `db_capstone`.
     */
    protected function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[RefreshDatabase::class])) {
            $connection = config('database.default');
            $database = $connection === 'mysql'
                ? config('database.connections.mysql.database')
                : null;

            if ($database !== 'db_capstone_test') {
                throw new RuntimeException(
                    'Refusing to run database tests against ['.($database ?? $connection).']. '
                    .'Tests must use the MySQL database db_capstone_test.'
                );
            }
        }

        return parent::setUpTraits();
    }
}
