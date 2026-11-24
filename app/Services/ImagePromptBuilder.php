<?php

namespace App\Services;

use App\Models\Location;
use App\Support\WeatherCode;
use Illuminate\Support\Carbon;

/**
 * Turns the weather of the saved locations into one image prompt.
 *
 * Composition: a single square illustration split into a 2x2 grid, one panel
 * per location in dashboard order. Every detail in a panel is derived from the
 * Open-Meteo numbers: sky/precipitation from the WMO code, how people dress
 * from the temperature, how things move from the wind, and the light from the
 * local time of day. No language model is involved in building the prompt.
 */
class ImagePromptBuilder
{
    private const POSITIONS = ['Top-left', 'Top-right', 'Bottom-left', 'Bottom-right'];

    /**
     * @param  list<array{location: Location, weather: array}>  $entries  in slot order
     */
    public function build(array $entries): string
    {
        $count = count($entries);
        $layout = $count === 4 ? 'a 2x2 grid of four equal panels' : "{$count} equal panels side by side";

        $lines = [
            "A single square illustration divided into {$layout} separated by thin white borders.",
            'Each panel shows a recognisable street-level scene of a different place with its current weather.',
            'Consistent style across all panels: detailed modern gouache illustration, soft textures, cinematic lighting.',
            '',
        ];

        foreach (array_values($entries) as $i => $entry) {
            $lines[] = $this->panel(self::POSITIONS[$i] ?? 'Panel '.($i + 1), $entry['location'], $entry['weather']);
        }

        $lines[] = '';
        $lines[] = 'Do not include any text, letters, numbers, labels or signs with writing in the image.';

        return implode("\n", $lines);
    }

    private function panel(string $position, Location $location, array $weather): string
    {
        $hour = Carbon::parse($weather['local_time'])->setTimezone($location->timezone)->hour;

        return sprintf(
            '%s panel: %s. %s, %s. It is %s (%s°C, today between %s°C and %s°C), so %s. Wind %s km/h: %s.',
            $position,
            $location->label(),
            ucfirst($this->timeOfDay($hour, $weather['is_day'])),
            WeatherCode::visual($weather['weather_code']),
            $this->temperatureFeel($weather['temperature']),
            $this->number($weather['temperature']),
            $this->number($weather['low']),
            $this->number($weather['high']),
            $this->clothing($weather['temperature']),
            $this->number($weather['wind_speed']),
            $this->windEffect($weather['wind_speed']),
        );
    }

    private function timeOfDay(int $hour, bool $isDay): string
    {
        if (! $isDay) {
            return $hour >= 17 && $hour < 21 ? 'dusk with streetlights coming on' : 'night under street lights';
        }

        return match (true) {
            $hour < 8 => 'early morning with low golden light',
            $hour < 12 => 'morning daylight',
            $hour < 17 => 'afternoon daylight',
            default => 'late evening with warm low sunlight',
        };
    }

    private function temperatureFeel(float $c): string
    {
        return match (true) {
            $c <= -10 => 'bitterly cold',
            $c < 0 => 'freezing',
            $c < 10 => 'cold',
            $c < 18 => 'cool',
            $c < 25 => 'pleasantly warm',
            $c < 32 => 'hot',
            default => 'scorching hot',
        };
    }

    private function clothing(float $c): string
    {
        return match (true) {
            $c < 0 => 'the few people outside wear heavy winter coats, hats and scarves',
            $c < 10 => 'people wear warm coats',
            $c < 18 => 'people wear light jackets',
            $c < 25 => 'people wear t-shirts',
            default => 'people wear summer clothes and look for shade',
        };
    }

    private function windEffect(float $kmh): string
    {
        return match (true) {
            $kmh < 5 => 'the air is still',
            $kmh < 20 => 'a light breeze moves leaves and flags gently',
            $kmh < 40 => 'trees sway and flags stretch out in the wind',
            $kmh < 62 => 'strong wind bends trees and pushes people as they walk',
            default => 'a storm-force gale whips debris through the air',
        };
    }

    private function number(float $value): string
    {
        return (string) round($value);
    }
}
