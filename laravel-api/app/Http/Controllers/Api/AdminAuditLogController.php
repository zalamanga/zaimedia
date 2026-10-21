<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Support\Pagination;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $action = $request->query('action');
        $actorId = $request->query('actor_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        return AdminAuditLog::with('actor:id,full_name,email,role')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($s) => $s
                ->where('action', 'like', "%{$term}%")
                ->orWhere('target_type', 'like', "%{$term}%")
                ->orWhereHas('actor', fn ($a) => $a->where('full_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))))
            ->when($action && $action !== 'all', fn ($q) => $q->where('action', $action))
            ->when($actorId, fn ($q) => $q->where('actor_id', $actorId))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest('created_at')
            ->paginate(Pagination::perPage($request, 20));
    }
}
