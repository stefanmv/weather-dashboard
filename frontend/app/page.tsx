'use client';

import { useCallback, useEffect, useState } from 'react';
import { PlaceSearch } from '@/components/PlaceSearch';
import { WeatherCard } from '@/components/WeatherCard';
import { ImagePanel } from '@/components/ImagePanel';
import { PlusIcon } from '@/components/Icons';
import { api, ApiError, Place, SavedLocation } from '@/lib/api';

const SLOTS = [1, 2, 3, 4];

export default function Dashboard() {
  const [locations, setLocations] = useState<SavedLocation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [addingSlot, setAddingSlot] = useState<number | null>(null);

  // Locations live in the backend database, so they survive a reload.
  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setLocations(await api.listLocations());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not load your locations.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const sortBySlot = (list: SavedLocation[]) => [...list].sort((a, b) => a.slot - b.slot);

  async function add(place: Place, slot: number) {
    const saved = await api.addLocation(place, slot); // errors are shown by PlaceSearch
    setLocations((prev) => sortBySlot([...prev, saved]));
    setAddingSlot(null);
  }

  async function replace(location: SavedLocation, place: Place) {
    const saved = await api.replaceLocation(location.id, place); // errors are shown by PlaceSearch
    setLocations((prev) => sortBySlot(prev.map((l) => (l.id === location.id ? saved : l))));
  }

  async function remove(location: SavedLocation) {
    try {
      await api.removeLocation(location.id);
    } catch (err) {
      // Already gone on the server is fine; anything else is reported.
      if (!(err instanceof ApiError && err.status === 404)) {
        setError(err instanceof ApiError ? err.message : 'Could not remove the location.');
        return;
      }
    }
    setLocations((prev) => prev.filter((l) => l.id !== location.id));
  }

  const bySlot = new Map(locations.map((l) => [l.slot, l]));

  return (
    <main>
      <header className="page-head">
        <div>
          <p className="eyebrow">Live weather · AI picture</p>
          <h1>Weather dashboard</h1>
        </div>
        <p className="count">
          <strong>{locations.length}</strong> / {SLOTS.length} locations
        </p>
      </header>

      {error && (
        <div className="alert banner" role="alert">
          <p>{error}</p>
          <button className="btn small" onClick={load}>
            Retry
          </button>
        </div>
      )}

      <section className="grid" aria-busy={loading}>
        {SLOTS.map((slot) => {
          if (loading) return <article key={slot} className="card card-plain skeleton-card" />;

          const location = bySlot.get(slot);
          if (location) {
            return (
              <WeatherCard
                // Remount when the place changes so the card fetches the new weather.
                key={`${location.id}:${location.latitude},${location.longitude}`}
                location={location}
                onRemove={() => remove(location)}
                onReplace={(place) => replace(location, place)}
              />
            );
          }

          if (addingSlot === slot) {
            return (
              <article key={`add-${slot}`} className="card card-plain">
                <PlaceSearch onPick={(place) => add(place, slot)} onCancel={() => setAddingSlot(null)} />
              </article>
            );
          }

          return (
            <button key={`empty-${slot}`} className="card card-empty" onClick={() => setAddingSlot(slot)}>
              <PlusIcon />
              <span>Add a location</span>
            </button>
          );
        })}
      </section>

      <ImagePanel ready={locations.length === SLOTS.length} missing={SLOTS.length - locations.length} />

      <footer className="footer">
        Weather from <a href="https://open-meteo.com" target="_blank" rel="noreferrer">Open-Meteo</a>. Images by OpenAI.
      </footer>
    </main>
  );
}
