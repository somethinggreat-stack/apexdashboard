<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function storePopup(Request $request)
    {
        $data = $request->validate([
            'firstName'   => ['required', 'string', 'max:100'],
            'lastName'    => ['required', 'string', 'max:100'],
            'email'       => ['required', 'email', 'max:255'],
            'phone'       => ['required', 'string', 'max:40'],
            'score'       => ['nullable', 'string', 'max:50'],
            'goal'        => ['nullable', 'string', 'max:50'],
            'urgency'     => ['nullable', 'string', 'max:50'],
            'source_page' => ['nullable', 'string', 'max:255'],
        ]);

        $lead = Lead::create([
            'type'        => Lead::TYPE_POPUP,
            'first_name'  => $data['firstName'],
            'last_name'   => $data['lastName'],
            'email'       => $data['email'],
            'phone'       => $data['phone'],
            'score'       => $data['score']   ?? null,
            'goal'        => $data['goal']    ?? null,
            'urgency'     => $data['urgency'] ?? null,
            'source_page' => $data['source_page'] ?? $request->headers->get('referer'),
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 512),
        ]);

        return response()->json(['ok' => true, 'id' => $lead->id], 201);
    }

    public function storeContact(Request $request)
    {
        $data = $request->validate([
            'firstName'   => ['required', 'string', 'max:100'],
            'lastName'    => ['required', 'string', 'max:100'],
            'email'       => ['required', 'email', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:40'],
            'subject'     => ['required', 'string', 'max:120'],
            'message'     => ['nullable', 'string', 'max:5000'],
            'source_page' => ['nullable', 'string', 'max:255'],
        ]);

        $lead = Lead::create([
            'type'        => Lead::TYPE_CONTACT,
            'first_name'  => $data['firstName'],
            'last_name'   => $data['lastName'],
            'email'       => $data['email'],
            'phone'       => $data['phone']   ?? '',
            'subject'     => $data['subject'],
            'message'     => $data['message'] ?? null,
            'source_page' => $data['source_page'] ?? $request->headers->get('referer'),
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 512),
        ]);

        return response()->json(['ok' => true, 'id' => $lead->id], 201);
    }

    public function dashboard(Request $request)
    {
        $type = $request->query('type', 'all');
        $allowedTypes = ['all', Lead::TYPE_POPUP, Lead::TYPE_CONTACT];
        if (! in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        $query = Lead::query()->orderByDesc('id');
        if ($type !== 'all') {
            $query->where('type', $type);
        }
        $leads = $query->limit(500)->get();

        $now     = now();
        $today   = $now->copy()->startOfDay();
        $weekAgo = $now->copy()->subDays(7);

        $stats = [
            'total'       => Lead::count(),
            'popup'       => Lead::where('type', Lead::TYPE_POPUP)->count(),
            'contact'     => Lead::where('type', Lead::TYPE_CONTACT)->count(),
            'today'       => Lead::where('created_at', '>=', $today)->count(),
            'last_7_days' => Lead::where('created_at', '>=', $weekAgo)->count(),
            'urgent'      => Lead::where('urgency', 'asap')->count(),
        ];

        $goalCounts = Lead::selectRaw('goal, COUNT(*) as c')
            ->whereNotNull('goal')
            ->groupBy('goal')
            ->orderByDesc('c')
            ->pluck('c', 'goal');

        $scoreCounts = Lead::selectRaw('score, COUNT(*) as c')
            ->whereNotNull('score')
            ->groupBy('score')
            ->orderByDesc('c')
            ->pluck('c', 'score');

        return view('admin.leads.index', [
            'leads'        => $leads,
            'stats'        => $stats,
            'goalCounts'   => $goalCounts,
            'scoreCounts'  => $scoreCounts,
            'activeType'   => $type,
        ]);
    }
}
