<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ImportUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:import {url : The URL of the JSON file containing users} {--limit= : Maximum number of users to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from a public JSON URL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = $this->argument('url');
        $limit = $this->option('limit');

        if ($limit !== null && (! is_numeric($limit) || (int) $limit < 0)) {
            $this->error('The limit must be a non-negative integer.');

            return Command::FAILURE;
        }

        $limit = $limit !== null ? (int) $limit : null;

        $this->info("Fetching users from: {$url}");

        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            $this->error("Failed to connect to URL: {$e->getMessage()}");

            return Command::FAILURE;
        }

        if (! $response->successful()) {
            $this->error("Failed to fetch users. HTTP status: {$response->status()}");

            return Command::FAILURE;
        }

        $users = $response->json();

        if (! is_array($users) || (! empty($users) && ! array_is_list($users))) {
            $this->error('Invalid JSON response: expected an array of users.');

            return Command::FAILURE;
        }

        if (empty($users)) {
            $this->warn('No users found in the JSON response.');

            return Command::SUCCESS;
        }

        $usersToImport = $limit !== null ? array_slice($users, 0, $limit) : $users;
        $totalToImport = count($usersToImport);

        $this->info("Importing {$totalToImport} user(s)...");

        $imported = 0;
        $skipped = 0;

        foreach ($usersToImport as $userData) {
            $result = $this->importUser($userData);

            if ($result === true) {
                $imported++;
            } else {
                $skipped++;
                $this->warn("Skipped: {$result}");
            }
        }

        $this->newLine();
        $this->info("Import completed: {$imported} imported, {$skipped} skipped.");

        return Command::SUCCESS;
    }

    /**
     * Import a single user from the JSON data.
     *
     * @param  array  $userData
     * @return bool|string True on success, error message on failure
     */
    protected function importUser(array $userData): bool|string
    {
        $validator = Validator::make($userData, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            $errors = implode(', ', $validator->errors()->all());

            return "Validation failed for user: {$errors}";
        }

        $email = $userData['email'];

        if (User::where('email', $email)->exists()) {
            return "User with email '{$email}' already exists";
        }

        User::create([
            'name' => $userData['name'],
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $this->line("  Imported: {$userData['name']} <{$email}>");

        return true;
    }
}
