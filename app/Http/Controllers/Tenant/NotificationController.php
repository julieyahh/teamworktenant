<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $user = Auth::user();

        // Fetch latest notifications (newest first)
        $notifications = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Mark all unread notifications as read
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if($user->role === 'manager'){
            return view('manager.admin-notification', compact('notifications'));
        }else{
            return view('tenant.notifications', compact('notifications'));
        }
    }

}
