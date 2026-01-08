<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportUsersCommandTest extends TestCase
{
    use DatabaseMigrations;

    protected array $sampleUsers = [
        [
            'id' => 1,
            'name' => 'Leanne Graham',
            'username' => 'Bret',
            'email' => 'Sincere@april.biz',
            'address' => [
                'street' => 'Kulas Light',
                'suite' => 'Apt. 556',
                'city' => 'Gwenborough',
                'zipcode' => '92998-3874',
            ],
            'phone' => '1-770-736-8031 x56442',
            'website' => 'hildegard.org',
            'company' => [
                'name' => 'Romaguera-Crona',
            ],
        ],
        [
            'id' => 2,
            'name' => 'Ervin Howell',
            'username' => 'Antonette',
            'email' => 'Shanna@melissa.tv',
            'address' => [
                'street' => 'Victor Plains',
                'suite' => 'Suite 879',
                'city' => 'Wisokyburgh',
                'zipcode' => '90566-7771',
            ],
            'phone' => '010-692-6593 x09125',
            'website' => 'anastasia.net',
            'company' => [
                'name' => 'Deckow-Crist',
            ],
        ],
        [
            'id' => 3,
            'name' => 'Clementine Bauch',
            'username' => 'Samantha',
            'email' => 'Nathan@yesenia.net',
            'address' => [
                'street' => 'Douglas Extension',
                'suite' => 'Suite 847',
                'city' => 'McKenziehaven',
                'zipcode' => '59590-4157',
            ],
            'phone' => '1-463-123-4447',
            'website' => 'ramiro.info',
            'company' => [
                'name' => 'Romaguera-Jacobson',
            ],
        ],
    ];

    public function test_it_imports_users_from_valid_json_url(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response($this->sampleUsers, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutput('Fetching users from: https://example.com/users')
            ->expectsOutput('Importing 3 user(s)...')
            ->expectsOutput('Import completed: 3 imported, 0 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', ['name' => 'Leanne Graham', 'email' => 'Sincere@april.biz']);
        $this->assertDatabaseHas('users', ['name' => 'Ervin Howell', 'email' => 'Shanna@melissa.tv']);
        $this->assertDatabaseHas('users', ['name' => 'Clementine Bauch', 'email' => 'Nathan@yesenia.net']);
    }

    public function test_it_respects_the_limit_parameter(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response($this->sampleUsers, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users', '--limit' => 2])
            ->expectsOutput('Importing 2 user(s)...')
            ->expectsOutput('Import completed: 2 imported, 0 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['email' => 'Sincere@april.biz']);
        $this->assertDatabaseHas('users', ['email' => 'Shanna@melissa.tv']);
        $this->assertDatabaseMissing('users', ['email' => 'Nathan@yesenia.net']);
    }

    public function test_it_handles_limit_of_zero(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response($this->sampleUsers, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users', '--limit' => 0])
            ->expectsOutput('Importing 0 user(s)...')
            ->expectsOutput('Import completed: 0 imported, 0 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_handles_limit_greater_than_available_users(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response($this->sampleUsers, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users', '--limit' => 100])
            ->expectsOutput('Importing 3 user(s)...')
            ->expectsOutput('Import completed: 3 imported, 0 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 3);
    }

    public function test_it_handles_empty_json_array(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response([], 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutput('No users found in the JSON response.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_handles_unreachable_url(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response(null, 500),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutput('Failed to fetch users. HTTP status: 500')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_handles_404_response(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response(null, 404),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutput('Failed to fetch users. HTTP status: 404')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_handles_malformed_json_response(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response('not a json', 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutput('Invalid JSON response: expected an array of users.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_handles_json_object_instead_of_array(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response(['name' => 'John'], 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutput('Invalid JSON response: expected an array of users.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_skips_users_with_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'Sincere@april.biz']);

        Http::fake([
            'https://example.com/users' => Http::response($this->sampleUsers, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutput('Importing 3 user(s)...')
            ->expectsOutputToContain("User with email 'Sincere@april.biz' already exists")
            ->expectsOutput('Import completed: 2 imported, 1 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 3);
    }

    public function test_it_skips_users_with_missing_name(): void
    {
        $usersWithMissingName = [
            ['id' => 1, 'email' => 'test@example.com'],
            ['id' => 2, 'name' => 'Valid User', 'email' => 'valid@example.com'],
        ];

        Http::fake([
            'https://example.com/users' => Http::response($usersWithMissingName, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutputToContain('Validation failed')
            ->expectsOutput('Import completed: 1 imported, 1 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'valid@example.com']);
    }

    public function test_it_skips_users_with_missing_email(): void
    {
        $usersWithMissingEmail = [
            ['id' => 1, 'name' => 'No Email User'],
            ['id' => 2, 'name' => 'Valid User', 'email' => 'valid@example.com'],
        ];

        Http::fake([
            'https://example.com/users' => Http::response($usersWithMissingEmail, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutputToContain('Validation failed')
            ->expectsOutput('Import completed: 1 imported, 1 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'valid@example.com']);
    }

    public function test_it_skips_users_with_invalid_email(): void
    {
        $usersWithInvalidEmail = [
            ['id' => 1, 'name' => 'Invalid Email User', 'email' => 'not-an-email'],
            ['id' => 2, 'name' => 'Valid User', 'email' => 'valid@example.com'],
        ];

        Http::fake([
            'https://example.com/users' => Http::response($usersWithInvalidEmail, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users'])
            ->expectsOutputToContain('Validation failed')
            ->expectsOutput('Import completed: 1 imported, 1 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'valid@example.com']);
    }

    public function test_it_rejects_invalid_limit_parameter(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response($this->sampleUsers, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users', '--limit' => 'invalid'])
            ->expectsOutput('The limit must be a non-negative integer.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_rejects_negative_limit_parameter(): void
    {
        Http::fake([
            'https://example.com/users' => Http::response($this->sampleUsers, 200),
        ]);

        $this->artisan('users:import', ['url' => 'https://example.com/users', '--limit' => -5])
            ->expectsOutput('The limit must be a non-negative integer.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_works_with_jsonplaceholder_structure(): void
    {
        Http::fake([
            'https://jsonplaceholder.typicode.com/users' => Http::response($this->sampleUsers, 200),
        ]);

        $this->artisan('users:import', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            '--limit' => 2,
        ])
            ->expectsOutput('Import completed: 2 imported, 0 skipped.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 2);
    }
}
