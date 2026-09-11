<?php

namespace App\Modules\NotificationCenter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'group' => $request->query('group'),
            'unread' => $request->boolean('unread'),
        ];

        $items = Notification::query()
            ->where('user_id', $user->id)
            ->when($filters['group'], fn ($q, $g) => $q->whereIn('group', (array) $g))
            ->when($filters['unread'], fn ($q) => $q->whereNull('read_at'))
            ->with('actor:id,name,avatar')
            ->latest()
            ->paginate(40)
            ->withQueryString();

        $stats = [
            'total' => Notification::where('user_id', $user->id)->count(),
            'unread' => $this->notifications->unreadCount($user),
            'by_group' => Notification::query()
                ->where('user_id', $user->id)
                ->groupBy('group')
                ->select('group', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread'))
                ->get(),
        ];

        return Inertia::render('notifications/index', [
            'notifications' => $items,
            'stats' => $stats,
            'filters' => $filters,
            'groups' => Notification::GROUPS,
        ]);
    }

    public function dropdown(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = Notification::query()
            ->where('user_id', $user->id)
            ->with('actor:id,name,avatar')
            ->latest()
            ->limit(12)
            ->get();

        return response()->json([
            'items' => $items,
            'unread' => $this->notifications->unreadCount($user),
            'by_group' => $this->notifications->unreadByGroup($user),
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markAll(Request $request): RedirectResponse
    {
        $this->notifications->markRead($request->user());

        Activity::log('notifications.read-all', [
            'subject_user_id' => $request->user()->id,
            'module' => 'notifications',
            'description' => 'Marked all notifications as read',
        ]);

        return back();
    }

    public function destroy(Request $request, Notification $notification): RedirectResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->delete();

        return back();
    }

    public function clearAll(Request $request): RedirectResponse
    {
        $this->notifications->clearAll($request->user());

        Activity::log('notifications.cleared', [
            'subject_user_id' => $request->user()->id,
            'module' => 'notifications',
            'description' => 'Cleared all notifications',
        ]);

        return back();
    }
}
