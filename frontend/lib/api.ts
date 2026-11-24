// Thin client for the Laravel API. The browser only ever talks to our backend;
// Open-Meteo and OpenAI are called server-side.

// Empty in the production build: the page is served by Laravel, so /api is same-origin.
const API_URL = (process.env.NEXT_PUBLIC_API_URL ?? '').replace(/\/$/, '');

export type Place = {
  id?: number;
  name: string;
  admin1: string | null;
  country: string | null;
  country_code: string | null;
  latitude: number;
  longitude: number;
  timezone: string;
};

export type SavedLocation = Place & { id: number; slot: number };

export type Weather = {
  location_id: number;
  temperature: number;
  weather_code: number;
  condition: string;
  wind_speed: number;
  is_day: boolean;
  high: number;
  low: number;
  timezone: string;
  local_time: string;
  units: { temperature: string; wind_speed: string };
};

export type GeneratedImage = { prompt: string; image: string; model: string };

/** Error with a message that is safe to show to the user. */
export class ApiError extends Error {
  constructor(message: string, public status: number, public body: Record<string, unknown> = {}) {
    super(message);
  }
}

async function request<T>(path: string, init: RequestInit & { timeoutMs?: number } = {}): Promise<T> {
  const { timeoutMs = 20000, ...rest } = init;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);

  let res: Response;
  try {
    res = await fetch(`${API_URL}/api${path}`, {
      ...rest,
      signal: controller.signal,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...rest.headers },
    });
  } catch (e) {
    const timedOut = e instanceof DOMException && e.name === 'AbortError';
    throw new ApiError(
      timedOut ? 'The request took too long. Please try again.' : `Cannot reach the API${API_URL ? ` at ${API_URL}` : ""}. Is the Laravel server running?`,
      0,
    );
  } finally {
    clearTimeout(timer);
  }

  if (res.status === 204) return undefined as T;

  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    // Laravel validation errors: { message, errors: { field: [msg] } }
    const firstError = body?.errors ? (Object.values(body.errors)[0] as string[])?.[0] : undefined;
    throw new ApiError(firstError ?? body?.message ?? `Request failed (HTTP ${res.status}).`, res.status, body);
  }
  return body as T;
}

export const api = {
  searchPlaces: (q: string) =>
    request<{ results: Place[] }>(`/geocode?q=${encodeURIComponent(q)}`).then((r) => r.results),

  listLocations: () => request<{ locations: SavedLocation[] }>('/locations').then((r) => r.locations),

  addLocation: (place: Place, slot?: number) =>
    request<{ location: SavedLocation }>('/locations', {
      method: 'POST',
      body: JSON.stringify({ ...place, slot }),
    }).then((r) => r.location),

  replaceLocation: (id: number, place: Place) =>
    request<{ location: SavedLocation }>(`/locations/${id}`, {
      method: 'PUT',
      body: JSON.stringify(place),
    }).then((r) => r.location),

  removeLocation: (id: number) => request<void>(`/locations/${id}`, { method: 'DELETE' }),

  weather: (id: number) => request<{ weather: Weather }>(`/locations/${id}/weather`).then((r) => r.weather),

  // Image generation regularly takes 20-60s, so allow longer than the backend's own timeout.
  generateImage: () => request<GeneratedImage>('/image', { method: 'POST', timeoutMs: 160000 }),
};
