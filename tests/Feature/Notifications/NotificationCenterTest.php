<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\RoleName;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private User $user;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $unit = Unit::factory()->create(['is_active' => true]);
        $this->user = $this->userWithRole(RoleName::Operator, $unit);
        $this->other = $this->userWithRole(RoleName::Operator, $unit);
    }

    public function test_the_page_lists_only_the_users_own_notifications(): void
    {
        $this->notify($this->user, 'operator', 'Milik saya');
        $this->notify($this->other, 'operator', 'Milik orang lain');

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifications/index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.title', 'Milik saya')
                ->where('notifications.data.0.read', false)
                ->where('unread_by_module.operator', 1)
                ->where('settings.digest_time', '06:30')
                ->has('settings.categories', count(NotificationCategory::cases())));
    }

    public function test_the_page_filters_by_module_and_unread(): void
    {
        $this->notify($this->user, 'operator', 'Absen');
        $this->notify($this->user, 'operasi', 'Jadwal');
        $this->user->notifications()->where('data->module', 'operasi')->first()->markAsRead();

        $this->actingAs($this->user)
            ->get(route('notifications.index', ['module' => 'operasi']))
            ->assertInertia(fn ($page) => $page->has('notifications.data', 1)->where('notifications.data.0.title', 'Jadwal'));

        $this->actingAs($this->user)
            ->get(route('notifications.index', ['status' => 'unread']))
            ->assertInertia(fn ($page) => $page->has('notifications.data', 1)->where('notifications.data.0.title', 'Absen'));
    }

    public function test_the_bell_shares_the_unread_count_and_the_feed(): void
    {
        $this->notify($this->user, 'operator', 'Satu');
        $this->notify($this->user, 'operator', 'Dua');

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('notifications.unread', 2));

        $this->actingAs($this->user)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread', 2)
            ->assertJsonCount(2, 'items');
    }

    public function test_opening_marks_read_and_goes_to_the_page(): void
    {
        $id = $this->notify($this->user, 'operator', 'Absen', '/operator/presensi');

        $this->actingAs($this->user)
            ->get(route('notifications.open', $id))
            ->assertRedirect('/operator/presensi');

        $this->assertNotNull($this->user->notifications()->find($id)->read_at);
    }

    public function test_an_external_url_is_never_followed(): void
    {
        $id = $this->notify($this->user, 'operator', 'Aneh', 'https://contoh.invalid/phish');

        $this->actingAs($this->user)
            ->get(route('notifications.open', $id))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_another_users_notification_cannot_be_opened_or_read(): void
    {
        $id = $this->notify($this->other, 'operator', 'Rahasia');

        $this->actingAs($this->user)->get(route('notifications.open', $id))->assertNotFound();
        $this->actingAs($this->user)->postJson(route('notifications.read', $id))->assertNotFound();
        $this->assertNull($this->other->notifications()->find($id)->read_at);
    }

    public function test_mark_all_read_touches_only_the_users_own(): void
    {
        $this->notify($this->user, 'operator', 'A');
        $this->notify($this->other, 'operator', 'B');

        $this->actingAs($this->user)->postJson(route('notifications.read-all'))->assertJsonPath('unread', 0);

        $this->assertSame(1, $this->other->unreadNotifications()->count());
    }

    public function test_settings_are_saved_and_validated(): void
    {
        $this->actingAs($this->user)
            ->patch(route('notifications.settings.update'), ['disabled' => ['jadwal'], 'digest_time' => '07:15'])
            ->assertRedirect();

        $this->user->refresh();
        $this->assertSame('07:15', $this->user->digestTime());
        $this->assertFalse($this->user->wantsNotification(NotificationCategory::Jadwal));
        $this->assertTrue($this->user->wantsNotification(NotificationCategory::Absensi));

        $this->actingAs($this->user)
            ->patch(route('notifications.settings.update'), ['disabled' => ['bukan-kategori'], 'digest_time' => '25:00'])
            ->assertSessionHasErrors(['disabled.0', 'digest_time']);
    }

    public function test_a_device_subscribes_and_unsubscribes(): void
    {
        $subscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'AuthSecret'],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->actingAs($this->user)->postJson(route('notifications.push.store'), $subscription)->assertJsonPath('subscribed', true);
        $this->assertSame(1, $this->user->pushSubscriptions()->count());

        // The same phone signing in with another account moves the subscription to that account.
        $this->actingAs($this->other)->postJson(route('notifications.push.store'), $subscription)->assertOk();
        $this->assertSame(0, $this->user->pushSubscriptions()->count());
        $this->assertSame(1, $this->other->pushSubscriptions()->count());

        // A user can only remove their own device.
        $this->actingAs($this->user)->deleteJson(route('notifications.push.destroy'), ['endpoint' => $subscription['endpoint']])->assertOk();
        $this->assertSame(1, $this->other->pushSubscriptions()->count());

        $this->actingAs($this->other)->deleteJson(route('notifications.push.destroy'), ['endpoint' => $subscription['endpoint']])->assertOk();
        $this->assertSame(0, $this->other->pushSubscriptions()->count());
    }

    public function test_the_test_notification_reaches_the_bell(): void
    {
        $this->actingAs($this->user)->postJson(route('notifications.test'))->assertOk()->assertJsonPath('unread', 1);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->getJson(route('notifications.feed'))->assertUnauthorized();
    }

    private function notify(User $user, string $module, string $title, ?string $url = null): string
    {
        $user->notify(new ReminderNotification(NotificationCategory::Absensi, $module, $title, 'Isi', $url, 'k:'.Str::uuid()));

        return (string) $user->notifications()->where('data->title', $title)->value('id');
    }
}
