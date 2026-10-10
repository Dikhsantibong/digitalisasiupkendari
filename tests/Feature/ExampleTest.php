<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There is no landing page: the root URL sends guests to the login page and
 * signed-in users to their dashboard.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_root_url_sends_guests_to_the_login_page(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_the_root_url_sends_signed_in_users_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
