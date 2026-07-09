@extends($adminLayout ?? 'layouts.admin')

@section('title', "Today's Queue")

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Today's Queue â€” {{ $selectedClient->business_name }}</h2>
    </div>

    <p class="muted" style="padding: 0 4px 12px;">
        Active clients behind on their schedule, grouped by the week they should already have logged.
    </p>

    @forelse ($endUsers as $weekLabel => $group)
        <h3 style="margin: 18px 0 8px;">{{ $weekLabel }} <span class="muted" style="font-weight:400;">â€” {{ $group->count() }} client(s)</span></h3>
        <div class="table-scroll"><table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Days Active</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group as $eu)
                    <tr class="row-link" data-href="{{ route('admin.end-users.show', $eu) }}">
                        <td><a href="{{ route('admin.end-users.show', $eu) }}" class="name-link">{{ $eu->full_name }}</a></td>
                        <td>{{ $eu->days_active }}</td>
                        <td><span class="pill pill-incomplete">{{ $eu->incomplete_reason }}</span></td>
                        <td class="no-link">
                            <a href="{{ route('admin.end-users.show', $eu) }}" class="btn btn-sm">Open</a>
                            <a href="{{ route('admin.end-users.status-report', $eu) }}" target="_blank" class="btn btn-sm">Report</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @empty
        <div class="empty" style="padding: 40px; text-align:center;">
            <p style="font-size: 16px;">All caught up. No clients are behind on their schedule.</p>
        </div>
    @endforelse
</div>

@push('head')
<style>
    .name-link { color:#1e40af; text-decoration:none; font-weight:600; }
    .name-link:hover { text-decoration:underline; }
</style>
@endpush
@endsection
