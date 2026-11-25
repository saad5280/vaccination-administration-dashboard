<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! config('vaccination.demo_mode')) {
            return;
        }

        foreach (config('vaccination.demo_accounts') as $demoAccount) {
            $role = Role::query()->where('name', $demoAccount['role'])->firstOrFail();
            $user = User::query()->updateOrCreate(
                ['email' => $demoAccount['email']],
                [
                    'name' => $demoAccount['name'],
                    'password' => $demoAccount['password'],
                    'is_active' => true,
                ],
            );

            $user->roles()->sync([$role->id]);
        }
    }
}
