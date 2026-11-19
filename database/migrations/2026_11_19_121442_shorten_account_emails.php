<?php

use App\Support\AccountEmail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Short login e-mails (user, 2026-11-19): every `{jabatan}.{unit}@upkendari.co.id`
     * account becomes the short form of {@see AccountEmail} (e.g.
     * koordinator-logistik.pltd-poasia-cont@… → koord.log.poasiacont@…; the
     * TL Ophar → tl.ophar.{unit}). Passwords are untouched; other addresses
     * (personal e-mails, kantor induk accounts) stay. An address already taken
     * by another account is left as it is. A legacy address still logs in
     * (FortifyServiceProvider::authenticateUsing).
     */
    public function up(): void
    {
        $ophar = DB::table('role_assignments')
            ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
            ->where('roles.name', 'tl_ophar')
            ->pluck('role_assignments.user_id')
            ->flip();

        $users = DB::table('users')->where('email', 'like', '%@'.AccountEmail::DOMAIN)->orderBy('id')->get(['id', 'email']);
        $taken = DB::table('users')->pluck('id', 'email')->mapWithKeys(fn ($id, $email): array => [strtolower((string) $email) => $id])->all();

        foreach ($users as $user) {
            $short = AccountEmail::short($user->email, isset($ophar[$user->id]));
            if ($short === strtolower($user->email) || (isset($taken[$short]) && $taken[$short] !== $user->id)) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update(['email' => $short]);
            unset($taken[strtolower($user->email)]);
            $taken[$short] = $user->id;
        }
    }

    /**
     * The long addresses are not restored (a legacy address still logs in).
     */
    public function down(): void
    {
        //
    }
};
