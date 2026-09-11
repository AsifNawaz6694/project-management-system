<?php

namespace App\Modules\Communication\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\Teams\Models\Team;
use App\Modules\UserManagement\Models\Activity;

class MentionParser
{
    /** Below this a token is too ambiguous to resolve to anybody. */
    private const MIN_TOKEN = 3;

    /** A single comment cannot notify more people than this. */
    private const MAX_RECIPIENTS = 25;

    /**
     * User IDs mentioned in a body, including everyone in a mentioned team.
     *
     * Matching used to be `name LIKE %token%` with no minimum length, so "@a"
     * quietly notified ten arbitrary people. A token now has to match the local
     * part of an email, a whole name, or the start of a name word.
     *
     * @return array<int, int>
     */
    public static function extract(string $body): array
    {
        $tokens = self::tokens($body);

        if ($tokens === []) {
            return [];
        }

        $userIds = self::matchUsers($tokens);
        $teamIds = self::matchTeams($tokens);

        if ($teamIds !== []) {
            $userIds = array_merge(
                $userIds,
                Team::query()
                    ->whereIn('id', $teamIds)
                    ->with('members:id')
                    ->get()
                    ->flatMap(fn (Team $t) => $t->members->pluck('id'))
                    ->all(),
            );
        }

        return array_values(array_slice(array_unique(array_map('intval', $userIds)), 0, self::MAX_RECIPIENTS));
    }

    /**
     * Teams named in a body, so callers can say "the whole team was mentioned".
     *
     * @return array<int, int>
     */
    public static function extractTeams(string $body): array
    {
        return self::matchTeams(self::tokens($body));
    }

    /**
     * @return array<int, string>
     */
    private static function tokens(string $body): array
    {
        if (! preg_match_all('/@([a-z0-9._-]{'.self::MIN_TOKEN.',40})/i', $body, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, int>
     */
    private static function matchUsers(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        return User::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $escaped = self::escape($token);

                    // dana.scully in dana.scully@example.com
                    $q->orWhere('email', 'like', $escaped.'@%');

                    // The whole name, or a name written without spaces.
                    $q->orWhere('name', '=', $token);
                    $q->orWhereRaw('REPLACE(LOWER(name), " ", "") = ?', [mb_strtolower(str_replace(['.', '-', '_'], '', $token))]);

                    // The start of any word in the name: "@dana" finds "Dana Scully".
                    $q->orWhere('name', 'like', $escaped.'%');
                    $q->orWhere('name', 'like', '% '.$escaped.'%');
                }
            })
            ->limit(self::MAX_RECIPIENTS)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, int>
     */
    private static function matchTeams(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        return Team::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('slug', '=', $token)
                        ->orWhere('name', '=', $token)
                        ->orWhereRaw('REPLACE(LOWER(name), " ", "-") = ?', [mb_strtolower($token)]);
                }
            })
            ->limit(10)
            ->pluck('id')
            ->all();
    }

    private static function escape(string $token): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token);
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $context
     */
    public static function notify(array $userIds, string $description, array $context = [], ?int $actorId = null): void
    {
        $userIds = array_filter(array_unique($userIds));

        if ($userIds === []) {
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
