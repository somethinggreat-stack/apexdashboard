@extends('layouts.admin')

@section('title', 'Select Business Owner')

@section('content')
<div class="card">
    <div class="card-header">
        <h2>Select a Business Owner to Work On</h2>
        <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Manage Business Owners</a>
    </div>

    @if ($clients->isEmpty())
        <div class="empty">
            No business owners yet.
            <a href="{{ route('admin.clients.index') }}">Add one to get started.</a>
        </div>
    @else
        <div class="picker-grid">
            @foreach ($clients as $client)
                <form method="POST" action="{{ route('admin.client-selector.select', $client->id) }}" class="picker-card-form">
                    @csrf
                    <button type="submit" class="picker-card">
                        <div class="picker-card-name">{{ $client->business_name }}</div>
                        <div class="picker-card-meta">
                            <span>{{ $client->end_users_count }} clients</span>
                            <span class="pill pill-{{ $client->status }}">{{ $client->status }}</span>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>
@endsection
