@extends('layouts.admin')

@section('title', "Today's Queue")

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Today's Queue</h2>
    </div>

    <p class="muted" style="padding: 0 4px 12px;">
        Every active client behind on their schedule, grouped by the week they should already have logged.
        Click a name to jump into that BO's dashboard and the client's detail page.
    </p>

    @forelse ($endUsers as $weekLabel => $group)
        <h3 style="margin: 18px 0 8px;">{{ $weekLabel }} <span class="muted" style="font-weight:400;">— {{ $group->count() }} client(s)</span></h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Business Owner</th>
                    <th>Days Active</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group as $eu)
                    <tr>
                        <td>
                            <a href="#" onclick="event.preventDefault(); document.getElementById('sel-{{ $eu->id }}').submit();">
                                {{ $eu->full_name }}
                            </a>
                            <form id="sel-{{ $eu->id }}" method="POST" action="{{ route('admin.client-selector.select', $eu->client_id) }}" style="display:none;">
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ route('admin.end-users.show', $eu->id) }}">
                            </form>
                        </td>
                        <td>{{ $eu->client?->business_name ?? '—' }}</td>
                        <td>{{ $eu->days_active }}</td>
                        <td><span class="pill pill-incomplete">{{ $eu->incomplete_reason }}</span></td>
                        <td>
                            <a href="{{ route('admin.end-users.status-report', $eu) }}" target="_blank" class="btn btn-sm">Report</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div class="empty" style="padding: 40px; text-align:center;">
            <p style="font-size: 16px;">All caught up. No clients are behind on their schedule.</p>
        </div>
    @endforelse
</div>
@endsection
