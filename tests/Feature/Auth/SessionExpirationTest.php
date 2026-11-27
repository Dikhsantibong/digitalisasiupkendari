<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SessionExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_check_returns_ok_for_authenticated_user_and_unauthorized_for_guest(): void
    {
        $this->getJson(route('session.check'))->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('session.check'))
            ->assertOk()
            ->assertExactJson(['authenticated' => true]);
    }

    public function test_inertia_request_with_expired_session_redirects_to_login_with_notice(): void
    {
        $response = $this->get(route('dashboard'), ['X-Inertia' => 'true']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas(
            'session_expired',
            'Sesi Anda telah berakhir. Silakan masuk kembali untuk melanjutkan.',
        );

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('sessionExpired', 'Sesi Anda telah berakhir. Silakan masuk kembali untuk melanjutkan.')
            );
    }

    public function test_fresh_guest_visit_redirects_to_login_without_session_expired_notice(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('session_expired');

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('sessionExpired', null)
            );
    }

    public function test_intentional_logout_does_not_flash_session_expired_notice(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $response->assertSessionMissing('session_expired');
    }

    public function test_page_expired_419_redirects_unauthenticated_request_to_login_with_notice(): void
    {
        Route::middleware('web')->post('/_test/session-expired-419', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });

        $response = $this->post('/_test/session-expired-419');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas(
            'session_expired',
            'Sesi Anda telah berakhir. Silakan masuk kembali untuk melanjutkan.',
        );
    }
}
