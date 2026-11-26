<?php

namespace Tests\Feature\Settings;

use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia']);
        $user = User::factory()->create([
            'name' => 'Sarono',
            'employee_id' => 'OPR-POA-A1',
            'position' => 'Operator',
        ]);
        Employee::factory()->forUnit($unit)->create([
            'user_id' => $user->id,
            'name' => 'Sarono',
            'nip' => 'OPR-POA-A1',
            'position' => 'Operator',
            'division' => 'Operasi',
            'regu' => 'A',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('accountInfo.name', 'Sarono')
                ->where('accountInfo.nip', 'OPR-POA-A1')
                ->where('accountInfo.position', 'Operator')
                ->where('accountInfo.division', 'Operasi')
                ->where('accountInfo.unit', 'PLTD Poasia')
                ->where('accountInfo.regu', 'Regu A')
        );
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_only_can_be_updated_keeping_existing_name()
    {
        $user = User::factory()->create(['name' => 'Pegawai Tetap']);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'email' => 'pegawai.baru@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Pegawai Tetap', $user->name);
        $this->assertSame('pegawai.baru@example.com', $user->email);
    }

    public function test_user_can_save_and_remove_canvas_signature_from_profile()
    {
        Storage::fake('public');

        $unit = Unit::factory()->create();
        $user = User::factory()->create();
        $employee = Employee::factory()->forUnit($unit)->create([
            'user_id' => $user->id,
        ]);

        // 1x1 transparent PNG base64
        $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this
            ->actingAs($user)
            ->post(route('profile.update.signature'), [
                'signature_base64' => $pngBase64,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $employee->refresh();
        $this->assertNotNull($employee->signature_path);
        Storage::disk('public')->assertExists($employee->signature_path);

        $oldPath = $employee->signature_path;

        $removeResponse = $this
            ->actingAs($user)
            ->post(route('profile.update.signature'), [
                'remove_signature' => true,
            ]);

        $removeResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $employee->refresh();
        $this->assertNull($employee->signature_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
