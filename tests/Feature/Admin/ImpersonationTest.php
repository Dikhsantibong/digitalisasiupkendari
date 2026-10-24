<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Pindah Akun (Super Admin "masuk sebagai"): who may switch, to whom, the
 * password, the session hygiene, what stays closed meanwhile, the time limit,
 * the way back and the audit trail.
 */
class ImpersonationTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private User $admin;

    private User $operator;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->create(['is_active' => true]);
        $this->admin = $this->userWithRole(RoleName::SuperAdmin);
        $this->admin->forceFill(['password' => Hash::make('rahasia-admin')])->save();
        $this->operator = $this->userWithRole(RoleName::Operator, $this->unit);
    }

    public function test_a_super_admin_switches_with_the_password_and_comes_back(): void
    {
        $this->actingAs($this->admin);
        $before = session()->getId();

        $this->post(route('admin.impersonation.start', $this->operator), ['password' => 'rahasia-admin'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($this->operator);
        $this->assertNotSame($before, session()->getId(), 'The session id is regenerated (no fixation).');
        $this->assertSame($this->admin->id, session(Impersonation::SESSION_KEY)['admin_id']);
        $this->assertNull(session('auth.password_confirmed_at'), "The admin's password confirmation is set aside.");
        $this->assertNull($this->operator->fresh()->last_login_at, 'A switch is not a sign in of the user.');

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $this->operator->id)
            ->where('auth.impersonator.id', $this->admin->id)
            ->where('auth.impersonator.minutes_left', Impersonation::MAX_MINUTES)
            ->where('auth.canImpersonate', false)
            ->where('auth.isSuperAdmin', false));

        $this->post(route('impersonation.stop'))->assertRedirect(route('admin.users.index'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session(Impersonation::SESSION_KEY));
        $this->assertNotNull(session('auth.password_confirmed_at'), 'Back home the confirmation returns.');
        $this->assertTrue(ActivityLog::query()->where('properties->impersonation', 'start')->where('user_id', $this->admin->id)->exists());
        $this->assertTrue(ActivityLog::query()->where('properties->impersonation', 'stop')->where('user_id', $this->admin->id)->exists());

        // A recent confirmation makes the next switch quick (no password).
        $this->post(route('admin.impersonation.start', $this->operator))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->operator);
    }

    public function test_the_password_is_required_and_checked(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.impersonation.start', $this->operator))->assertSessionHasErrors('password');
        $this->post(route('admin.impersonation.start', $this->operator), ['password' => 'salah'])->assertSessionHasErrors('password');

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session(Impersonation::SESSION_KEY));
        // Only a POST starts it: a link cannot.
        $this->get('/admin/pindah-akun/'.$this->operator->id)->assertStatus(405);
    }

    public function test_only_a_super_admin_switches_and_never_into_a_super_admin_an_inactive_account_or_itself(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('admin.impersonation.start', $this->operator), ['password' => 'password'])->assertForbidden();
        $this->actingAs($tl)->getJson(route('admin.impersonation.users'))->assertForbidden();
        $this->assertAuthenticatedAs($tl);

        $otherAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $inactive = $this->userWithRole(RoleName::Operator, $this->unit);
        $inactive->forceFill(['is_active' => false])->save();

        foreach ([$otherAdmin, $inactive, $this->admin] as $target) {
            $this->actingAs($this->admin)->post(route('admin.impersonation.start', $target), ['password' => 'rahasia-admin'])->assertForbidden();
            $this->assertAuthenticatedAs($this->admin);
        }
    }

    public function test_the_search_lists_only_the_accounts_one_may_switch_to(): void
    {
        $this->userWithRole(RoleName::SuperAdmin)->forceFill(['name' => 'Admin Kedua'])->save();
        $inactive = $this->userWithRole(RoleName::Operator, $this->unit);
        $inactive->forceFill(['is_active' => false, 'name' => 'Operator Nonaktif'])->save();
        $this->operator->forceFill(['name' => 'Operator Aktif'])->save();

        $this->actingAs($this->admin)->getJson(route('admin.impersonation.users', ['q' => 'operator']))
            ->assertOk()
            ->assertJsonPath('password_confirmed', false)
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.name', 'Operator Aktif')
            ->assertJsonMissingPath('users.0.password');

        $ids = collect($this->actingAs($this->admin)->getJson(route('admin.impersonation.users'))->json('users'))->pluck('id');
        $this->assertFalse($ids->contains($this->admin->id));
    }

    public function test_the_borrowed_account_settings_and_a_second_switch_stay_closed(): void
    {
        $this->startAsOperator();
        $target = $this->userWithRole(RoleName::Operator, $this->unit);

        $this->post(route('admin.impersonation.start', $target), ['password' => 'rahasia-admin'])->assertForbidden();
        $this->getJson(route('admin.impersonation.users'))->assertForbidden();
        $this->patch(route('profile.update'), ['name' => 'Diubah', 'email' => 'baru@example.com'])->assertForbidden();
        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
        $this->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'Baru12345!', 'password_confirmation' => 'Baru12345!'])->assertForbidden();
        $this->post(route('two-factor.enable'))->assertForbidden();
        $this->post(route('password.confirm.store'), ['password' => 'password'])->assertForbidden();
        $this->postJson(route('notifications.push.store'), ['endpoint' => 'https://push.example/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertForbidden();

        $this->assertSame($this->operator->email, $this->operator->fresh()->email);
        $this->assertAuthenticatedAs($this->operator);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_saves_made_meanwhile_are_logged_with_the_super_admin(): void
    {
        $this->startAsOperator();

        $this->post(route('operasi.input.flm-monitoring.store'), [
            'unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026,
            'rows' => [['mesin' => 'Pompa', 'tanggal' => '2026-08-02', 'masalah' => 'Bocor', 'status' => 'open']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $log = ActivityLog::query()->where('description', 'like', 'Menyimpan Monitoring FLM%')->sole();
        $this->assertSame($this->operator->id, $log->user_id);
        $this->assertSame($this->admin->id, $log->properties['impersonated_by']);
    }

    public function test_it_ends_by_itself_after_the_time_limit(): void
    {
        $this->startAsOperator();

        $this->travel(Impersonation::MAX_MINUTES + 1)->minutes();

        $this->get(route('dashboard'))->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session(Impersonation::SESSION_KEY));
    }

    public function test_a_super_admin_who_lost_the_role_or_was_deactivated_is_signed_out(): void
    {
        $this->startAsOperator();
        $this->admin->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        // The way back refuses too.
        $this->admin->forceFill(['is_active' => true])->save();
        $this->startAsOperator();
        $this->admin->roleAssignments()->delete();
        $this->admin->forgetAccessCache();

        $this->post(route('impersonation.stop'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_coming_back_needs_an_impersonation_session(): void
    {
        $this->actingAs($this->operator)->post(route('impersonation.stop'))->assertForbidden();
        $this->assertAuthenticatedAs($this->operator);
    }

    private function startAsOperator(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.impersonation.start', $this->operator), ['password' => 'rahasia-admin'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->operator);
    }
}
