<?php

namespace Tests\Feature;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function forecastJson(float $temp = 12.3, int $code = 61, float $wind = 18.0): array
    {
        return [
            'current' => ['time' => '2026-11-23T14:00', 'temperature_2m' => $temp, 'weather_code' => $code, 'wind_speed_10m' => $wind, 'is_day' => 1],
            'daily' => ['temperature_2m_max' => [15.1], 'temperature_2m_min' => [7.9]],
        ];
    }

    private function seedFourLocations(): void
    {
        foreach ([
            ['London', 'England', 'United Kingdom', 51.5, -0.12, 'Europe/London'],
            ['Tokyo', 'Tokyo', 'Japan', 35.68, 139.69, 'Asia/Tokyo'],
            ['Springfield', 'Illinois', 'United States', 39.8, -89.64, 'America/Chicago'],
            ['Reykjavik', null, 'Iceland', 64.13, -21.9, 'Atlantic/Reykjavik'],
        ] as $i => [$name, $admin1, $country, $lat, $lon, $tz]) {
            Location::create(['slot' => $i + 1, 'name' => $name, 'admin1' => $admin1, 'country' => $country,
                'latitude' => $lat, 'longitude' => $lon, 'timezone' => $tz]);
        }
    }

    public function test_geocode_returns_all_matches_so_the_user_can_pick(): void
    {
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response(['results' => [
            ['id' => 1, 'name' => 'Springfield', 'admin1' => 'Illinois', 'country' => 'United States', 'country_code' => 'US', 'latitude' => 39.8, 'longitude' => -89.6, 'timezone' => 'America/Chicago'],
            ['id' => 2, 'name' => 'Springfield', 'admin1' => 'Missouri', 'country' => 'United States', 'country_code' => 'US', 'latitude' => 37.2, 'longitude' => -93.3, 'timezone' => 'America/Chicago'],
        ]])]);

        $this->getJson('/api/geocode?q=Springfield')
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.1.admin1', 'Missouri');
    }

    public function test_geocode_for_a_place_that_does_not_exist_returns_an_empty_list(): void
    {
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response(['generationtime_ms' => 0.1])]);

        $this->getJson('/api/geocode?q=Xqzzyplorf')->assertOk()->assertExactJson(['results' => []]);
    }

    public function test_locations_are_limited_to_four_and_slots_are_reused_after_removal(): void
    {
        $this->seedFourLocations();
        $payload = ['name' => 'Paris', 'country' => 'France', 'latitude' => 48.85, 'longitude' => 2.35, 'timezone' => 'Europe/Paris'];

        $this->postJson('/api/locations', $payload)->assertStatus(422)->assertJsonValidationErrors('location');

        $this->deleteJson('/api/locations/'.Location::where('slot', 2)->value('id'))->assertNoContent();

        $this->postJson('/api/locations', $payload)->assertCreated()->assertJsonPath('location.slot', 2);
    }

    public function test_a_location_goes_into_the_slot_that_was_clicked(): void
    {
        $payload = ['name' => 'Paris', 'country' => 'France', 'latitude' => 48.85, 'longitude' => 2.35, 'timezone' => 'Europe/Paris', 'slot' => 3];

        $this->postJson('/api/locations', $payload)->assertCreated()->assertJsonPath('location.slot', 3);
    }

    public function test_a_location_can_be_changed_in_place_keeping_its_slot(): void
    {
        $this->seedFourLocations();
        $tokyo = Location::where('name', 'Tokyo')->first();
        $paris = ['name' => 'Paris', 'country' => 'France', 'latitude' => 48.85, 'longitude' => 2.35, 'timezone' => 'Europe/Paris'];

        $this->putJson("/api/locations/{$tokyo->id}", $paris)
            ->assertOk()
            ->assertJsonPath('location.name', 'Paris')
            ->assertJsonPath('location.slot', $tokyo->slot);

        $this->assertSame(4, Location::count());

        // Changing to a place that is already in another slot is rejected.
        $london = Location::where('name', 'London')->first();
        $this->putJson("/api/locations/{$london->id}", $paris)->assertStatus(422);
    }

    public function test_the_same_place_cannot_be_added_twice(): void
    {
        $payload = ['name' => 'Paris', 'country' => 'France', 'latitude' => 48.85, 'longitude' => 2.35, 'timezone' => 'Europe/Paris'];

        $this->postJson('/api/locations', $payload)->assertCreated();
        $this->postJson('/api/locations', $payload)->assertStatus(422);
    }

    public function test_weather_returns_numbers_units_and_local_time(): void
    {
        $this->seedFourLocations();
        Http::fake(['api.open-meteo.com/*' => Http::response($this->forecastJson())]);

        $this->getJson('/api/locations/'.Location::where('name', 'Tokyo')->value('id').'/weather')
            ->assertOk()
            ->assertJsonPath('weather.temperature', 12.3)
            ->assertJsonPath('weather.condition', 'Slight rain')
            ->assertJsonPath('weather.high', 15.1)
            ->assertJsonPath('weather.units.wind_speed', 'km/h')
            ->assertJsonPath('weather.timezone', 'Asia/Tokyo');
    }

    public function test_weather_api_down_returns_a_clear_error(): void
    {
        $this->seedFourLocations();
        Http::fake(['api.open-meteo.com/*' => Http::response('oops', 500)]);

        $this->getJson('/api/locations/'.Location::first()->id.'/weather')
            ->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'weather service is unavailable'));
    }

    public function test_weather_api_timeout_returns_504(): void
    {
        $this->seedFourLocations();
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->getJson('/api/locations/'.Location::first()->id.'/weather')->assertStatus(504);
    }

    public function test_image_needs_four_locations(): void
    {
        $this->postJson('/api/image')->assertStatus(422);
    }

    public function test_image_is_generated_from_a_prompt_built_from_the_weather(): void
    {
        config(['services.openai.key' => 'test-key']);
        $this->seedFourLocations();
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecastJson(-4, 73, 45)),
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => 'aGVsbG8=']]]),
        ]);

        $response = $this->postJson('/api/image')->assertOk();

        $prompt = $response->json('prompt');
        $this->assertStringContainsString('2x2 grid', $prompt);
        $this->assertStringContainsString('Top-left panel: London, England, United Kingdom', $prompt);
        $this->assertStringContainsString('Bottom-right panel: Reykjavik, Iceland', $prompt);
        $this->assertStringContainsString('steady snowfall', $prompt);
        $this->assertStringContainsString('-4°C', $prompt);
        $this->assertSame('data:image/png;base64,aGVsbG8=', $response->json('image'));

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/images/generations'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['prompt'] === $prompt);
    }

    public function test_image_model_refusal_returns_message_and_prompt(): void
    {
        config(['services.openai.key' => 'test-key']);
        $this->seedFourLocations();
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecastJson()),
            'api.openai.com/*' => Http::response(['error' => ['code' => 'moderation_blocked', 'message' => 'blocked']], 400),
        ]);

        $this->postJson('/api/image')
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'refused'))
            ->assertJsonStructure(['message', 'prompt']);
    }

    public function test_image_model_timeout_returns_504_with_prompt(): void
    {
        config(['services.openai.key' => 'test-key']);
        $this->seedFourLocations();
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecastJson()),
            'api.openai.com/*' => fn () => throw new ConnectionException('timed out'),
        ]);

        $this->postJson('/api/image')->assertStatus(504)->assertJsonStructure(['message', 'prompt']);
    }

    public function test_missing_api_key_is_reported_and_no_key_leaks(): void
    {
        config(['services.openai.key' => null]);
        $this->seedFourLocations();
        Http::fake(['api.open-meteo.com/*' => Http::response($this->forecastJson())]);

        $this->postJson('/api/image')->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'OPENAI_API_KEY'));
    }
}
