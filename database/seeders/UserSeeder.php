<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create(['email', 'virachamreun@gmail.com', [
            'name' => "Chamreun Vira",
            'password' => Hash::make('Vira168168'),
            'role' => Role::Admin->value
        ]]);
    }
}
