<?php

namespace App\Modules\NotificationCenter\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;

class NotificationService
{
    /**
     * Push a notification to one or many users.
     *
     * @param  int|array<int>  $userIds
     */
    public function push(int|array $userIds, array $payload, ?int $actorId = null): void
    {
        $ids = array_filter(array_unique((array) $userIds));
        if (empty($ids)) {
            return;
        }

        if ($actorId !== null) {
            $ids = array_values(array_diff($ids, [$actorId]));
            if (empty($ids)) {
                return;
            }
        }

        $rows = array_map(fn ($id) => array_merge([
            'user_id' => $id,
            'actor_id' => $actorId,
            'group' => $payload['group'] ?? Notification::GROUP_SYSTEM,
            'type' => $payload['type'],
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'icon' => $payload['icon'] ?? null,
            'tone' => $payload['tone'] ?? 'violet',
            'link' => $payload['link'] ?? null,
            'data' => isset($payload['data']) ? json_encode($payload['data']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]), $ids);

        Notification::query()->insert($rows);
    }

    public function markRead(User $user, ?int $id = null): void
    {
        $query = Notification::query()->where('user_id', $user->id)->whereNull('read_at');
        if ($id !== null) {
            $query->where('id', $id);
        }
        $query->update(['read_at' => now()]);
    }

    public function clear(User $user, int $id): void
    {
        Notification::query()->where('user_id', $user->id)->where('id', $id)->delete();
    }

    public function clearAll(User $user): void
    {
        Notification::query()->where('user_id', $user->id)->delete();
    }
}
