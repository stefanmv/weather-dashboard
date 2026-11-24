<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\OpenMeteo;
use Illuminate\Http\JsonResponse;

/** One request per location, so one failing location does not affect the others. */
class WeatherController extends Controller
{
    public function __invoke(Location $location, OpenMeteo $openMeteo): JsonResponse
    {
        return response()->json(['weather' => $openMeteo->weatherFor($location)]);
    }
}
