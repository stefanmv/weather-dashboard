<?php

namespace App\Services;

use App\Exceptions\UpstreamException;
use App\Models\Location;
use App\Support\WeatherCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Open-Meteo (https://open-meteo.com): geocoding and forecast.
 * All weather numbers on the dashboard and in the image prompt come from here.
 */
class OpenMeteo
{
    /**
     * Search places by name. Returns [] when nothing matches.
     *
     * @return list<array{id:int,name:string,admin1:?string,country:?string,country_code:?string,latitude:float,longitude:float,timezone:string}>
     */
    public function searchPlaces(string $query): array
    {
        $response = $this->get(config('services.open_meteo.geocoding_url'), [
            'name' => $query,
            'count' => 10,
            'language' => 'en',
            'format' => 'json',
        ], 'The place search service');

        return collect($response->json('results', []))
            ->map(fn (array $r) => [
                'id' => $r['id'],
                'name' => $r['name'],
                'admin1' => $r['admin1'] ?? null,
                'country' => $r['country'] ?? null,
                'country_code' => $r['country_code'] ?? null,
                'latitude' => (float) $r['latitude'],
                'longitude' => (float) $r['longitude'],
                'timezone' => $r['timezone'] ?? 'UTC',
            ])
            ->values()
            ->all();
    }

    /**
     * Current weather + today's high/low for a saved location.
     * Cached for 10 minutes; failures are not cached.
     */
    public function weatherFor(Location $location): array
    {
        $key = sprintf('weather:%.4f:%.4f', $location->latitude, $location->longitude);

        $data = Cache::remember($key, now()->addMinutes(10), function () use ($location) {
            $response = $this->get(config('services.open_meteo.forecast_url'), [
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'current' => 'temperature_2m,weather_code,wind_speed_10m,is_day',
                'daily' => 'temperature_2m_max,temperature_2m_min',
                'temperature_unit' => 'celsius',
                'wind_speed_unit' => 'kmh',
                'timezone' => $location->timezone,
                'forecast_days' => 1,
            ], 'The weather service');

            $current = $response->json('current');
            $daily = $response->json('daily');

            if (! isset($current['temperature_2m'], $current['weather_code'], $daily['temperature_2m_max'][0])) {
                throw new UpstreamException('The weather service returned incomplete data.');
            }

            return [
                'temperature' => (float) $current['temperature_2m'],
                'weather_code' => (int) $current['weather_code'],
                'wind_speed' => (float) $current['wind_speed_10m'],
                'is_day' => (bool) ($current['is_day'] ?? true),
                'high' => (float) $daily['temperature_2m_max'][0],
                'low' => (float) $daily['temperature_2m_min'][0],
                'observed_at' => $current['time'] ?? null,
            ];
        });

        return [
            'location_id' => $location->id,
            ...$data,
            'condition' => WeatherCode::label($data['weather_code']),
            'timezone' => $location->timezone,
            // Computed per request, not cached, so it is always the current local time.
            'local_time' => Carbon::now($location->timezone)->toIso8601String(),
            'units' => ['temperature' => '°C', 'wind_speed' => 'km/h'],
        ];
    }

    private function get(string $url, array $query, string $serviceName): Response
    {
        try {
            $response = Http::timeout(config('services.open_meteo.timeout'))
                ->retry(2, 300, throw: false)
                ->acceptJson()
                ->get($url, $query);
        } catch (ConnectionException) {
            throw new UpstreamException("{$serviceName} did not answer in time. Please try again.", 504);
        }

        if ($response->failed()) {
            throw new UpstreamException("{$serviceName} is unavailable right now (HTTP {$response->status()}). Please try again.");
        }

        return $response;
    }
}
