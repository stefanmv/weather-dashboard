'use client';

import { useCallback, useEffect, useState } from 'react';
import { api, ApiError, Place, SavedLocation, Weather } from '@/lib/api';
import { flag, sceneFor } from '@/lib/scene';
import { ClockIcon, WeatherIcon, WindIcon } from './Icons';
import { PlaceSearch } from './PlaceSearch';

type Props = {
  location: SavedLocation;
  onRemove: () => void;
  onReplace: (place: Place) => Promise<void>;
};

function useLocalTime(timezone: string) {
  const format = useCallback(() => {
    const now = new Date();
    return {
      time: new Intl.DateTimeFormat(undefined, { timeZone: timezone, hour: '2-digit', minute: '2-digit' }).format(now),
      day: new Intl.DateTimeFormat(undefined, { timeZone: timezone, weekday: 'short', day: 'numeric', month: 'short' }).format(now),
    };
  }, [timezone]);
  const [value, setValue] = useState(format);
  useEffect(() => {
    setValue(format());
    const id = setInterval(() => setValue(format()), 15000);
    return () => clearInterval(id);
  }, [format]);
  return value;
}

const round = (n: number) => Math.round(n);

/** Fetches and shows the weather for one location. Its errors stay inside this card. */
export function WeatherCard({ location, onRemove, onReplace }: Props) {
  const [weather, setWeather] = useState<Weather | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [changing, setChanging] = useState(false);
  const local = useLocalTime(location.timezone);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setWeather(await api.weather(location.id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not load the weather.');
    } finally {
      setLoading(false);
    }
  }, [location.id]);

  useEffect(() => {
    load();
  }, [load]);

  if (changing) {
    return (
      <article className="card card-plain">
        <PlaceSearch label={`Replace ${location.name}`} onPick={onReplace} onCancel={() => setChanging(false)} />
      </article>
    );
  }

  const scene = weather && !error ? sceneFor(weather.weather_code, weather.is_day) : null;
  const region = [location.admin1, location.country].filter((v, i, a) => v && a.indexOf(v) === i).join(', ');
  const t = weather?.units.temperature ?? '°C';

  return (
    <article className={`card card-weather ${scene ? `scene-${scene}` : 'scene-loading'}`}>
      <header className="card-head">
        <div className="place">
          <h2>
            {location.name} <span className="flag">{flag(location.country_code)}</span>
          </h2>
          <p>{region}</p>
        </div>
        <div className="card-actions">
          <button className="chip" onClick={() => setChanging(true)}>
            Change
          </button>
          <button className="chip" onClick={onRemove} aria-label={`Remove ${location.name}`}>
            Remove
          </button>
        </div>
      </header>

      <p className="local-time">
        <ClockIcon /> <strong>{local.time}</strong> <span>· {local.day}</span>
      </p>

      {loading && !weather && (
        <div className="card-body">
          <div className="skeleton skeleton-temp" />
          <div className="skeleton skeleton-line" />
        </div>
      )}

      {error && (
        <div className="card-body">
          <div className="alert" role="alert">
            <p>{error}</p>
            <button className="btn small" onClick={load} disabled={loading}>
              {loading ? 'Retrying…' : 'Retry'}
            </button>
          </div>
        </div>
      )}

      {weather && !error && scene && (
        <>
          <div className="card-body">
            <div>
              <p className="temp">
                {round(weather.temperature)}
                <span>{t}</span>
              </p>
              <p className="condition">{weather.condition}</p>
            </div>
            <WeatherIcon scene={scene} className="weather-icon" />
          </div>

          <dl className="stats">
            <div>
              <dt>High / Low</dt>
              <dd>
                {round(weather.high)}
                {t} <span className="dim">/ {round(weather.low)}{t}</span>
              </dd>
            </div>
            <div>
              <dt>
                <WindIcon size={12} /> Wind
              </dt>
              <dd>
                {round(weather.wind_speed)} <span className="dim">{weather.units.wind_speed}</span>
              </dd>
            </div>
          </dl>
        </>
      )}
    </article>
  );
}
