<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['locations' => Location::orderBy('slot')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePlace($request);

        // The card the user clicked, if it is still free; otherwise the first free one.
        $requested = $request->integer('slot');
        $slot = $requested >= 1 && $requested <= Location::MAX && ! Location::where('slot', $requested)->exists()
            ? $requested
            : Location::nextFreeSlot();

        if ($slot === null) {
            throw ValidationException::withMessages([
                'location' => 'The dashboard already has '.Location::MAX.' locations. Remove or change one first.',
            ]);
        }

        $this->ensureNotOnDashboard($data);

        $location = Location::create([...$data, 'slot' => $slot]);

        return response()->json(['location' => $location], 201);
    }

    /** Replace the place in an existing slot in one step (the card's "Change" button). */
    public function update(Request $request, Location $location): JsonResponse
    {
        $data = $this->validatePlace($request);

        $this->ensureNotOnDashboard($data, except: $location);

        $location->update($data);

        return response()->json(['location' => $location->fresh()]);
    }

    public function destroy(Location $location): JsonResponse
    {
        $location->delete();

        return response()->json(null, 204);
    }

    private function validatePlace(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'admin1' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['required', 'timezone:all'],
        ]);
    }

    private function ensureNotOnDashboard(array $data, ?Location $except = null): void
    {
        $duplicate = Location::query()
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->whereBetween('latitude', [$data['latitude'] - 0.0001, $data['latitude'] + 0.0001])
            ->whereBetween('longitude', [$data['longitude'] - 0.0001, $data['longitude'] + 0.0001])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'location' => "{$data['name']} is already on the dashboard.",
            ]);
        }
    }
}
