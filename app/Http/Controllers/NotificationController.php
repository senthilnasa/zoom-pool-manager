<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Services\NotificationCenterService;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationCenterService $notificationCenter
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(20);

        $unreadCount = $this->notificationCenter->getUnreadCount($user);

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        }

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Notifications</h1>',
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->notificationCenter->markAsRead($user, $id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->notificationCenter->markAllAsRead($user);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'count' => $this->notificationCenter->getUnreadCount($user),
        ]);
    }
}
