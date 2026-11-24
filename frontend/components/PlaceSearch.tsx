'use client';

import { FormEvent, useState } from 'react';
import { api, ApiError, Place } from '@/lib/api';
import { flag } from '@/lib/scene';

type Props = {
  onPick: (place: Place) => Promise<void>;
  onCancel?: () => void;
  label?: string;
};

/** Search box + list of matches. The user picks one (there is more than one Springfield). */
export function PlaceSearch({ onPick, onCancel, label = 'Add a location' }: Props) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Place[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function search(e: FormEvent) {
    e.preventDefault();
    const q = query.trim();
    if (q.length < 2) {
      setError('Type at least 2 characters.');
      return;
    }
    setLoading(true);
    setError(null);
    setResults(null);
    try {
      setResults(await api.searchPlaces(q));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Search failed.');
    } finally {
      setLoading(false);
    }
  }

  async function pick(place: Place) {
    setSaving(true);
    setError(null);
    try {
      await onPick(place);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save this place.');
      setSaving(false);
    }
  }

  return (
    <div className="search">
      <div className="search-title">
        <span>{label}</span>
        {onCancel && (
          <button type="button" className="btn-ghost small" onClick={onCancel} disabled={saving}>
            Cancel
          </button>
        )}
      </div>

      <form onSubmit={search} className="search-form">
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="City or town, e.g. Springfield"
          aria-label="Place name"
          disabled={saving}
          autoFocus
        />
        <button type="submit" className="btn" disabled={loading || saving}>
          {loading ? <span className="spinner" aria-label="Searching" /> : 'Search'}
        </button>
      </form>

      {error && (
        <p className="alert" role="alert">
          {error}
        </p>
      )}

      {results && results.length === 0 && (
        <p className="hint">No place found for “{query.trim()}”. Check the spelling or try a nearby city.</p>
      )}

      {results && results.length > 0 && (
        <>
          <p className="hint">{results.length === 1 ? '1 match' : `${results.length} matches`}: pick the right one</p>
          <ul className="results">
            {results.map((p) => (
              <li key={p.id ?? `${p.latitude},${p.longitude}`}>
                <button type="button" onClick={() => pick(p)} disabled={saving}>
                  <span className="flag" aria-hidden>
                    {flag(p.country_code) || '📍'}
                  </span>
                  <span className="result-text">
                    <strong>{p.name}</strong>
                    <span>{[p.admin1, p.country].filter(Boolean).join(', ')}</span>
                  </span>
                  <small>
                    {p.latitude.toFixed(2)}°, {p.longitude.toFixed(2)}°
                  </small>
                </button>
              </li>
            ))}
          </ul>
        </>
      )}
    </div>
  );
}
