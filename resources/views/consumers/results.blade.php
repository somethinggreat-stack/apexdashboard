@extends('consumers.layout')

@section('title', 'Client Results | Apex Growth Solutions')
@section('description', 'Examples of real client credit report changes. Results vary and are not guaranteed.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Results</span>
        <h1>Real client credit reports</h1>
        <p>These are examples of score changes from client credit reports. Every credit profile is different.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="results-grid">
            @foreach (range(1, 8) as $n)
                <div class="result-shot"><img src="/Images/test{{ $n }}.png" alt="Client credit report example {{ $n }}" loading="lazy" width="900" height="480"></div>
            @endforeach
        </div>
        <p class="disclaimer"><strong>Disclaimer:</strong> These examples reflect individual client experiences and are not typical. Results vary based on each consumer's credit profile and circumstances. Apex Growth Solutions does not guarantee specific score increases, removals or outcomes.</p>
    </div>
</section>

@include('consumers.partials.cta', ['heading' => 'Want a review of your own reports?'])
@endsection
