<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * One reminder (schedule digest, personal duty, absen …) for one user: stored
 * for the bell / Notifikasi page and pushed to every device the user allowed
 * notifications on.
 *
 * Sent synchronously by the scheduler, so no queue worker is needed.
 */
class ReminderNotification extends Notification
{
    /**
     * @param  string  $module  operasi | har | k3 | pdm | logistik | operator
     * @param  string  $key  dedupe key, also the push `tag` so a newer copy replaces an older one on the phone
     */
    public function __construct(
        public NotificationCategory $category,
        public string $module,
        public string $title,
        public string $body,
        public ?string $url,
        public string $key,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->pushSubscriptions()->exists()
            ? ['database', WebPushChannel::class]
            : ['database'];
    }

    public function databaseType(User $notifiable): string
    {
        return 'reminder';
    }

    /**
     * @return array{category: string, module: string, title: string, body: string, url: string|null, key: string}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'category' => $this->category->value,
            'module' => $this->module,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'key' => $this->key,
        ];
    }

    public function toWebPush(User $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/apple-touch-icon.png')
            ->badge('/apple-touch-icon.png')
            ->tag($this->key)
            ->lang('id')
            ->data(['url' => $this->url ?? route('notifications.index', absolute: false), 'id' => $notification->id]);
    }
}
