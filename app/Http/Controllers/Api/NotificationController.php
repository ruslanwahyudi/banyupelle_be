<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifications;
use Illuminate\Http\Request;
use Validator;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notifications::where('user_id', $request->user()->id)
            ->latest()
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'List Notifikasi',
            'data' => [
                'unread_count' => $notifications->where('is_read', 0)->count(),
                'notifications' => $notifications
            ]
        ]);
    }

    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $notification = Notifications::findOrFail($request->id);
        
        $update = $notification->update([
            'is_read' => 1,
            'updated_at' => now()
        ]);

        if ($update) {
            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil ditandai telah dibaca',
                'data' => $notification
            ]);
        }
                

    }
} 