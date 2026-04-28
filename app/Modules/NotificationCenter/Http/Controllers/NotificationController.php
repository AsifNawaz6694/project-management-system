<?php

namespace App\Modules\NotificationCenter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $items = Notification::query()
            ->where('user_id', $user->id)
            ->with('actor:id,name,avatar')
            ->latest()
            ->paginate(40)
            ->withQueryString();

        $stats = [
            'total' => $items->total(),
            'unread' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
            'by_group' => Notification::query()
                ->where('user_id', $user->id)
                ->selectRaw('group, COUNT(*) as total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread')
                ->groupBy('group')
                ->get(),
        ];

        return Inertia::render('notifications/index', [
            'notifications' => $items,
            'stats' => $stats,
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
            'unread' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
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
