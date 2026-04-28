<?php

namespace App\Modules\Communication\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\UserManagement\Models\Activity;

class MentionParser
{
    /**
     * Parse @mentions out of a body, return matched user IDs.
     *
     * @return array<int>
     */
    public static function extract(string $body): array
    {
        if (! preg_match_all('/@([a-z0-9._-]+)/i', $body, $matches)) {
            return [];
        }

        $tokens = array_unique($matches[1]);
        if (empty($tokens)) {
            return [];
        }

        return User::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('email', 'like', $token.'%@%')
                        ->orWhere('name', 'like', "%{$token}%");
                }
            })
            ->limit(10)
            ->pluck('id')
            ->all();
    }

    public static function notify(array $userIds, string $description, array $context = [], ?int $actorId = null): void
    {
        $userIds = array_filter(array_unique($userIds));
        if (empty($userIds)) {
            return;
        }

        foreach ($userIds as $userId) {
            Activity::log('comment.mention', [
                'subject_user_id' => $userId,
                'module' => 'communication',
                'description' => $description,
                'properties' => $context,
            ]);
        }

        $link = null;
        if (isset($context['task_id'])) {
            $link = '/tasks/'.$context['task_id'];
        } elseif (isset($context['project_slug'])) {
            $link = '/projects/'.$context['project_slug'];
        }

        app(NotificationService::class)->push($userIds, [
            'group' => Notification::GROUP_MENTIONS,
            'type' => 'comment.mention',
            'title' => 'You were mentioned',
            'body' => $description,
            'icon' => 'at-sign',
            'tone' => 'pink',
            'link' => $link,
            'data' => $context,
        ], $actorId);
    }
}
