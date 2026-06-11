<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin' && $user->role !== 'store') {
            abort(403, 'Unauthorized access.');
        }

        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($user->role === 'store') {
            $query->where('user_id', $user->id);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('model_type', 'like', "%{$search}%")
                  ->orWhere('model_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Action filter
        if ($request->filled('action_filter')) {
            $query->where('action', $request->input('action_filter'));
        }

        $logs = $query->paginate(20)->withQueryString()->through(function ($log) {
            return [
                'id' => $log->id,
                'user_name' => $log->user->name ?? 'System',
                'action' => $log->action,
                'model_type' => class_basename($log->model_type),
                'model_id' => $log->model_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->format('d-m-Y H:i:s'),
            ];
        });

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'action_filter']),
        ]);
    }
}
