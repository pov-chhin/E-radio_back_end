<?php

namespace Src\CmsAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $request->user('admin');

        return view('cms-admin::notifications.index', [
            'notifications' => $admin->notifications()->paginate(20),
            'hasUnread' => $admin->unreadNotifications()->exists(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user('admin')->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user('admin')->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user('admin')->notifications()->findOrFail($notification)->delete();

        return back()->with('status', 'Notification deleted.');
    }
}
