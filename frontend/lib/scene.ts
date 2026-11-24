// Maps an Open-Meteo WMO weather code + day/night to a visual "scene":
// it picks the card's background and icon. Purely presentational.

export type Scene = 'clear-day' | 'clear-night' | 'partly-day' | 'partly-night' | 'cloudy' | 'fog' | 'rain' | 'snow' | 'storm';

export function sceneFor(code: number, isDay: boolean): Scene {
  if (code === 0) return isDay ? 'clear-day' : 'clear-night';
  if (code === 1 || code === 2) return isDay ? 'partly-day' : 'partly-night';
  if (code === 3) return 'cloudy';
  if (code === 45 || code === 48) return 'fog';
  if ((code >= 71 && code <= 77) || code === 85 || code === 86) return 'snow';
  if (code >= 95) return 'storm';
  if (code >= 51 && code <= 82) return 'rain';
  return 'cloudy';
}

/** "🇺🇸" from "US": helps tell places with the same name apart. */
export function flag(countryCode: string | null | undefined): string {
  if (!countryCode || countryCode.length !== 2) return '';
  return String.fromCodePoint(...[...countryCode.toUpperCase()].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
}
