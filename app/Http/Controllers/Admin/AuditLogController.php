<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $area = $request->query('area');
        $days = (int) $request->query('days', 14);

        $logs = AuditLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->when($area, fn ($b) => $b->where('area', $area))
            ->when($q !== '', fn ($b) => $b->where(function ($w) use ($q) {
                $w->where('actor_label', 'ilike', "%{$q}%")
                    ->orWhere('action', 'ilike', "%{$q}%")
                    ->orWhere('path', 'ilike', "%{$q}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', compact('logs', 'q', 'area', 'days'));
    }
}
