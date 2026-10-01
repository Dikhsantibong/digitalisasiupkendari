<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\ReminderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The signed-in user's own notifications: the bell feed, the Notifikasi page,
 * reading them, and the user's reminder preferences. A user only ever sees
 * and changes their own notifications.
 */
class NotificationController extends Controller
{
    /** Modules a notification can belong to, in filter order. */
    public const MODULES = ['operasi', 'operator', 'har', 'k3', 'pdm', 'logistik'];

    private const FEED_SIZE = 15;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $module = in_array($request->query('module'), self::MODULES, true) ? $request->query('module') : null;
        $unreadOnly = $request->query('status') === 'unread';

        $notifications = $user->notifications()
            ->when($module !== null, fn ($q) => $q->where('data->module', $module))
            ->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n): array => $this->present($n));

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'filters' => ['module' => $module, 'status' => $unreadOnly ? 'unread' : 'all'],
            'unread_by_module' => $user->unreadNotifications()->get(['data'])->countBy(fn (DatabaseNotification $n): string => (string) ($n->data['module'] ?? 'lainnya')),
            'settings' => [
                'digest_time' => $user->digestTime(),
                'categories' => array_map(fn (NotificationCategory $category): array => [
                    'key' => $category->value,
                    'label' => $category->label(),
                    'description' => $category->description(),
                    'allowed' => $user->hasPermissionTo($category->permission()),
                    'enabled' => ! in_array($category->value, $user->notification_settings['disabled'] ?? [], true),
                ], NotificationCategory::cases()),
            ],
        ]);
    }

    /**
     * The bell: unread count and the latest notifications.
     */
    public function feed(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(self::FEED_SIZE)->get()->map(fn (DatabaseNotification $n): array => $this->present($n)),
        ]);
    }

    /**
     * Open a notification (from the bell, the page or a phone push): mark it
     * read and go to its page.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $this->own($request, $notification);
        $record->markAsRead();
        $url = $record->data['url'] ?? null;

        return is_string($url) && Str::startsWith($url, '/') && ! Str::startsWith($url, '//')
            ? redirect($url)
            : redirect()->route('notifications.index');
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $this->own($request, $notification)->markAsRead();

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $module = in_array($request->input('module'), self::MODULES, true) ? $request->input('module') : null;

        $user->unreadNotifications()
            ->when($module !== null, fn ($q) => $q->where('data->module', $module))
            ->update(['read_at' => now()]);

        return $request->expectsJson()
            ? response()->json(['unread' => $user->unreadNotifications()->count()])
            : back();
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'disabled' => ['present', 'array'],
            'disabled.*' => ['string', Rule::enum(NotificationCategory::class)],
            'digest_time' => ['required', 'date_format:H:i'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['notification_settings' => [
            'disabled' => array_values(array_unique($validated['disabled'])),
            'digest_time' => $validated['digest_time'],
        ]])->save();

        return back();
    }

    /**
     * A test notification to the user's own devices, to check that push works.
     */
    public function test(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $delivered = true;

        try {
            $user->notify(new ReminderNotification(
                NotificationCategory::Jadwal,
                'umum',
                'Notifikasi aktif',
                'Pengingat jadwal & absen akan muncul seperti ini di perangkat Anda.',
                route('notifications.index', absolute: false),
                'test:'.Str::uuid(),
            ));
        } catch (Throwable $e) {
            // The bell copy is stored before the push; report the push failure without failing the request.
            report($e);
            $delivered = false;
        }

        return response()->json(['unread' => $user->unreadNotifications()->count(), 'pushed' => $delivered]);
    }

    /**
     * @return array{id: string, category: string|null, module: string, title: string, body: string, url: string|null, read: bool, created_at: string|null}
     */
    private function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'category' => $data['category'] ?? null,
            'module' => $data['module'] ?? 'umum',
            'title' => (string) ($data['title'] ?? 'Notifikasi'),
            'body' => (string) ($data['body'] ?? ''),
            'url' => $data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function own(Request $request, string $id): DatabaseNotification
    {
        /** @var User $user */
        $user = $request->user();

        return $user->notifications()->whereKey($id)->firstOrFail();
    }
}
