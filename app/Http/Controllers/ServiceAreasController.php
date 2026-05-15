<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ServiceAreasController extends Controller
{
    public function index()
    {
        $states = config('service_areas');
        return view('service-areas.index', compact('states'));
    }

    public function show(string $slug)
    {
        $states = config('service_areas');
        abort_unless(isset($states[$slug]), 404);

        $state = $states[$slug];

        // Pull three "neighbour" states for related links: same region, then nearby.
        $related = collect($states)
            ->where('region', $state['region'])
            ->reject(fn ($s) => $s['slug'] === $slug)
            ->shuffle()
            ->take(4)
            ->values()
            ->all();

        return view('service-areas.show', compact('state', 'related', 'states'));
    }
}
