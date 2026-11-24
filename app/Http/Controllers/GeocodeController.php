<?php

namespace App\Http\Controllers;

use App\Services\OpenMeteo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodeController extends Controller
{
    public function __invoke(Request $request, OpenMeteo $openMeteo): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json([
            'results' => $openMeteo->searchPlaces(trim($validated['q'])),
        ]);
    }
}
