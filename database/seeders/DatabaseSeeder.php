<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::updateOrInsert([
            'email' => 'lucasdesouzacastellani@gmail.com',
        ], [
            'name' => 'Lucas Castellani',
            'password' => Hash::make('password'),
        ]);

        Tenant::updateOrInsert([
            'uuid' => '1234567890',
        ], [
            'name' => 'Finance Pro',
        ]);

        User::first()->tenants()->attach(Tenant::first());
    }
}
