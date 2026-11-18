<?php

use App\Models\ServiceUnit;
use App\Models\User;
use Database\Seeders\AdminUlAccountSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One Admin UL account per service unit (user, 2026-11-18): the role
     * existed without any holder. Idempotent — existing accounts keep their
     * password; see {@see AdminUlAccountSeeder}.
     */
    public function up(): void
    {
        (new AdminUlAccountSeeder)->run();
    }

    public function down(): void
    {
        $emails = ServiceUnit::query()->get()->map(fn (ServiceUnit $serviceUnit): string => AdminUlAccountSeeder::emailFor($serviceUnit))->all();

        User::query()->whereIn('email', $emails)->get()->each(fn (User $user) => $user->delete());
    }
};
