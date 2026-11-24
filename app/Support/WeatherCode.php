<?php

namespace App\Support;

/**
 * WMO weather interpretation codes, as returned by Open-Meteo.
 * https://open-meteo.com/en/docs (section "WMO Weather interpretation codes")
 *
 * Each code maps to a short label for the dashboard and a visual phrase
 * used when building the image prompt.
 */
final class WeatherCode
{
    private const CODES = [
        0 => ['Clear sky', 'a clear sky'],
        1 => ['Mainly clear', 'a mostly clear sky with a few small clouds'],
        2 => ['Partly cloudy', 'a partly cloudy sky'],
        3 => ['Overcast', 'a heavy grey overcast sky'],
        45 => ['Fog', 'thick fog hiding the buildings'],
        48 => ['Depositing rime fog', 'freezing fog with frost on every surface'],
        51 => ['Light drizzle', 'a light drizzle and damp streets'],
        53 => ['Drizzle', 'steady drizzle and wet streets'],
        55 => ['Dense drizzle', 'dense drizzle and glistening wet streets'],
        56 => ['Light freezing drizzle', 'light freezing drizzle glazing the ground with ice'],
        57 => ['Freezing drizzle', 'freezing drizzle coating everything in ice'],
        61 => ['Slight rain', 'light rain falling'],
        63 => ['Rain', 'steady rain and puddles'],
        65 => ['Heavy rain', 'heavy pouring rain and flooded gutters'],
        66 => ['Light freezing rain', 'light freezing rain and icy pavements'],
        67 => ['Freezing rain', 'heavy freezing rain and ice-covered trees'],
        71 => ['Slight snow', 'light snowflakes drifting down'],
        73 => ['Snow', 'steady snowfall covering the streets'],
        75 => ['Heavy snow', 'heavy snowfall and deep snow drifts'],
        77 => ['Snow grains', 'fine snow grains in the air'],
        80 => ['Slight rain showers', 'passing light rain showers'],
        81 => ['Rain showers', 'rain showers under dark clouds'],
        82 => ['Violent rain showers', 'violent downpours under black clouds'],
        85 => ['Slight snow showers', 'brief snow showers'],
        86 => ['Heavy snow showers', 'heavy snow showers and poor visibility'],
        95 => ['Thunderstorm', 'a thunderstorm with lightning'],
        96 => ['Thunderstorm with slight hail', 'a thunderstorm with lightning and small hail'],
        99 => ['Thunderstorm with heavy hail', 'a violent thunderstorm with large hail'],
    ];

    public static function label(int $code): string
    {
        return self::CODES[$code][0] ?? 'Unknown';
    }

    public static function visual(int $code): string
    {
        return self::CODES[$code][1] ?? 'unremarkable weather';
    }
}
