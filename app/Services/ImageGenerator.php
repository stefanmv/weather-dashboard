<?php

namespace App\Services;

use App\Exceptions\UpstreamException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Generates an image with the OpenAI Images API (gpt-image-2 by default).
 * The API key is read from .env on the server and never sent to the browser.
 */
class ImageGenerator
{
    private const URL = 'https://api.openai.com/v1/images/generations';

    /** @return string a data: URL with the PNG image */
    public function generate(string $prompt): string
    {
        $key = config('services.openai.key');

        if (blank($key)) {
            throw new UpstreamException('Image generation is not configured: set OPENAI_API_KEY in .env and restart the server.', 503);
        }

        try {
            $response = Http::withToken($key)
                ->timeout(config('services.openai.timeout'))
                ->acceptJson()
                ->post(self::URL, [
                    'model' => config('services.openai.image_model'),
                    'prompt' => $prompt,
                    'size' => '1024x1024',
                    'quality' => config('services.openai.image_quality'),
                    'n' => 1,
                ]);
        } catch (ConnectionException) {
            throw new UpstreamException('The image model did not answer in time. Please try again.', 504);
        }

        if ($response->failed()) {
            $code = $response->json('error.code');
            $message = $response->json('error.message');

            throw match (true) {
                in_array($code, ['moderation_blocked', 'content_policy_violation'], true)
                    => new UpstreamException('The image model refused this prompt (content policy). Try different locations.', 422),
                $response->status() === 401
                    => new UpstreamException('The OpenAI API key was rejected. Check OPENAI_API_KEY in .env.', 502),
                $response->status() === 429
                    => new UpstreamException('The image model is rate-limited or out of quota. Please wait a moment and try again.', 429),
                default
                    => new UpstreamException('The image model failed'.($message ? ": {$message}" : " (HTTP {$response->status()})").'.', 502),
            };
        }

        $b64 = $response->json('data.0.b64_json');

        if (! $b64) {
            throw new UpstreamException('The image model returned no image.', 502);
        }

        return 'data:image/png;base64,'.$b64;
    }
}
