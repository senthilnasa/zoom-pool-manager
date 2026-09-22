<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Models\NotificationPreference;
use App\Domain\Users\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationCenterService
{
    /**
     * Critical notification types that cannot be disabled by user preferences.
     *
     * @var array<int, string>
     */
    protected const NON_DISABLEABLE_TYPES = [
        'security_alert',
        'mfa_reset',
        'password_reset',
        'approval_required',
        'overdue_approval',
        'host_key_revealed',
        'emergency_override',
    ];

    /**
     * Send an in-app notification to a user.
     *
     * @param  array<string, mixed>  $data
     */
    public function notify(User $user, string $type, string $title, string $message, array $data = []): ?DatabaseNotification
    {
        if (! $this->isChannelEnabled($user, 'in_app', $type)) {
            return null;
        }

        $payload = array_merge($data, [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'created_at' => Carbon::now()->toIso8601String(),
        ]);

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $payload,
            'read_at' => null,
        ]);

        return $notification;
    }

    /**
     * Check if user has enabled this channel and type.
     */
    public function isChannelEnabled(User $user, string $channel, string $type): bool
    {
        // Security and administrative notifications cannot be disabled
        if (in_array($type, self::NON_DISABLEABLE_TYPES, true)) {
            return true;
        }

        $pref = NotificationPreference::where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('notification_type', $type)
            ->first();

        return $pref ? $pref->enabled : true;
    }

    /**
     * Get unread notifications for a user.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function getUnread(User $user, int $limit = 20): Collection
    {
        return $user->unreadNotifications()->limit($limit)->get();
    }

    /**
     * Count unread notifications.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(User $user, string $notificationId): bool
    {
        $notification = $user->notifications()->where('id', $notificationId)->first();
        if ($notification) {
            $notification->markAsRead();

            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => Carbon::now()]);
    }
}
