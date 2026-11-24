<?php

namespace App\Http\Controllers;

use App\Exceptions\UpstreamException;
use App\Models\Location;
use App\Services\ImageGenerator;
use App\Services\ImagePromptBuilder;
use App\Services\OpenMeteo;
use Illuminate\Http\JsonResponse;

/**
 * Builds the prompt on the server from the saved locations and fresh weather
 * (the browser sends nothing but the click), then asks the image model.
 */
class ImageController extends Controller
{
    public function __invoke(OpenMeteo $openMeteo, ImagePromptBuilder $prompts, ImageGenerator $images): JsonResponse
    {
        $locations = Location::orderBy('slot')->get();

        if ($locations->count() < Location::MAX) {
            return response()->json([
                'message' => 'Add '.Location::MAX.' locations before generating an image.',
            ], 422);
        }

        $entries = [];
        foreach ($locations as $location) {
            try {
                $entries[] = ['location' => $location, 'weather' => $openMeteo->weatherFor($location)];
            } catch (UpstreamException $e) {
                return response()->json([
                    'message' => "Could not get the weather for {$location->label()}: {$e->getMessage()}",
                ], $e->status);
            }
        }

        $prompt = $prompts->build($entries);

        // Image generation can take a minute; don't let PHP's limit cut it off first.
        set_time_limit(config('services.openai.timeout') + 30);

        try {
            $image = $images->generate($prompt);
        } catch (UpstreamException $e) {
            // Still return the prompt so the user can see what was sent.
            return response()->json(['message' => $e->getMessage(), 'prompt' => $prompt], $e->status);
        }

        return response()->json([
            'prompt' => $prompt,
            'image' => $image,
            'model' => config('services.openai.image_model'),
        ]);
    }
}
