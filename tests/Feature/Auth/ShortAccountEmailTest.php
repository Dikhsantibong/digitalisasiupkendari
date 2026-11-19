<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\Unit;
use App\Models\User;
use App\Support\AccountEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Short login e-mails (user, 2026-11-19): `{jabatan}.{unit}@upkendari.co.id`
 * accounts get an abbreviated jabatan & unit; existing accounts are renamed by
 * migration and a legacy address still logs in to the same account.
 */
class ShortAccountEmailTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_the_jabatan_and_unit_are_abbreviated(): void
    {
        $cases = [
            'koordinator-logistik.pltd-poasia-cont@upkendari.co.id' => 'koord.log.poasiacont@upkendari.co.id',
            'project-leader.pltd-baubau@upkendari.co.id' => 'pl.baubau@upkendari.co.id',
            'operator1.pltd-kolaka@upkendari.co.id' => 'op1.kolaka@upkendari.co.id',
            'tl-operasi.pltd-kolaka@upkendari.co.id' => 'tl.ops.kolaka@upkendari.co.id',
            'office-har.pltm-rongi@upkendari.co.id' => 'ofc.har.pltmrongi@upkendari.co.id',
            'unit.pltg-kolaka@upkendari.co.id' => 'unit.pltgkolaka@upkendari.co.id',
            'admin.ul-pltd-wua-wua@upkendari.co.id' => 'admin.wuawua@upkendari.co.id',
            'manager.ul-baubau@upkendari.co.id' => 'mgr.baubau@upkendari.co.id',
            'yonatan-linggi-allo.pltd-poasia-cont@upkendari.co.id' => 'yonatan-linggi-allo.poasiacont@upkendari.co.id',
            // Unchanged: the kantor induk accounts and any other domain.
            'manager-up@upkendari.co.id' => 'manager-up@upkendari.co.id',
            'admin@gmail.com' => 'admin@gmail.com',
        ];

        foreach ($cases as $legacy => $short) {
            $this->assertSame($short, AccountEmail::short($legacy), $legacy);
            // Idempotent: a short address stays as it is.
            $this->assertSame($short, AccountEmail::short($short), $short);
        }

        $this->assertSame('tl.ophar.raha@upkendari.co.id', AccountEmail::short('tl-operasi.pltd-raha@upkendari.co.id', ophar: true));
        $this->assertSame('koord.pdm.baru@upkendari.co.id', AccountEmail::forUnit('koordinator-pdm', 'PLTD-BARU'));
        $this->assertSame('pltmgbaubau', AccountEmail::unitKey('PLTMG-BAUBAU'));
    }

    public function test_the_migration_shortens_the_existing_accounts_and_keeps_the_rest(): void
    {
        $this->seedAccessControl();
        $raha = Unit::factory()->create(['code' => 'PLTD-RAHA']);
        $ophar = User::factory()->create(['email' => 'tl-operasi.pltd-raha@upkendari.co.id']);
        $ophar->assignRole(RoleName::TeamLeaderOphar, $raha);
        $pl = User::factory()->create(['email' => 'project-leader.pltd-baubau@upkendari.co.id']);
        $personal = User::factory()->create(['email' => 'budi@gmail.com']);
        $global = User::factory()->create(['email' => 'manager-up@upkendari.co.id']);
        // The short address is already taken by another account: left as it is.
        $taken = User::factory()->create(['email' => 'op1.kolaka@upkendari.co.id']);
        $clash = User::factory()->create(['email' => 'operator1.pltd-kolaka@upkendari.co.id']);

        (require database_path('migrations/2026_11_19_121442_shorten_account_emails.php'))->up();

        $this->assertSame('tl.ophar.raha@upkendari.co.id', $ophar->fresh()->email);
        $this->assertSame('pl.baubau@upkendari.co.id', $pl->fresh()->email);
        $this->assertSame('budi@gmail.com', $personal->fresh()->email);
        $this->assertSame('manager-up@upkendari.co.id', $global->fresh()->email);
        $this->assertSame('op1.kolaka@upkendari.co.id', $taken->fresh()->email);
        $this->assertSame('operator1.pltd-kolaka@upkendari.co.id', $clash->fresh()->email);
    }

    public function test_a_legacy_or_short_address_logs_in_and_a_wrong_password_does_not(): void
    {
        $this->seedAccessControl();
        $raha = Unit::factory()->create(['code' => 'PLTD-RAHA']);
        $pl = User::factory()->create(['email' => 'pl.baubau@upkendari.co.id']);
        $ophar = User::factory()->create(['email' => 'tl.ophar.raha@upkendari.co.id']);
        $ophar->assignRole(RoleName::TeamLeaderOphar, $raha);

        foreach (['pl.baubau@upkendari.co.id' => $pl, 'project-leader.pltd-baubau@upkendari.co.id' => $pl, 'PROJECT-LEADER.PLTD-BAUBAU@upkendari.co.id' => $pl, 'tl-operasi.pltd-raha@upkendari.co.id' => $ophar] as $email => $user) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'password'])->assertSessionHasNoErrors();
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }

        $this->post(route('login.store'), ['email' => 'project-leader.pltd-baubau@upkendari.co.id', 'password' => 'salah']);
        $this->assertGuest();
        $this->post(route('login.store'), ['email' => 'project-leader.pltd-wuawua@upkendari.co.id', 'password' => 'password']);
        $this->assertGuest();
    }
}
