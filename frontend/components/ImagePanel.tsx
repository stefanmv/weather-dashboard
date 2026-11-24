'use client';

import { useEffect, useState } from 'react';
import { api, ApiError, GeneratedImage } from '@/lib/api';
import { SparkleIcon } from './Icons';

type Props = { ready: boolean; missing: number };

/** Generates one image from the weather of the four locations and shows it next to its prompt. */
export function ImagePanel({ ready, missing }: Props) {
  const [result, setResult] = useState<GeneratedImage | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [failedPrompt, setFailedPrompt] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [elapsed, setElapsed] = useState(0);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    if (!loading) return;
    setElapsed(0);
    const id = setInterval(() => setElapsed((s) => s + 1), 1000);
    return () => clearInterval(id);
  }, [loading]);

  async function generate() {
    setLoading(true);
    setError(null);
    setFailedPrompt(null);
    try {
      setResult(await api.generateImage());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Image generation failed.');
      if (err instanceof ApiError && typeof err.body.prompt === 'string') setFailedPrompt(err.body.prompt);
    } finally {
      setLoading(false);
    }
  }

  async function copyPrompt(text: string) {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      /* clipboard not available: ignore */
    }
  }

  const showImage = !!result && !failedPrompt;
  const prompt = failedPrompt ?? result?.prompt ?? null;

  return (
    <section className="image-panel">
      <div className="image-head">
        <div>
          <h2>Weather picture</h2>
          <p className="muted">One image, four panels, built from the live numbers above.</p>
        </div>
        <button className="btn btn-primary" onClick={generate} disabled={!ready || loading}>
          {loading ? (
            <>
              <span className="spinner" /> Generating… {elapsed}s
            </>
          ) : (
            <>
              <SparkleIcon /> {result ? 'Generate again' : 'Generate image'}
            </>
          )}
        </button>
      </div>

      {!ready && (
        <p className="hint">
          Add {missing} more location{missing === 1 ? '' : 's'} to generate an image.
        </p>
      )}

      {error && (
        <p className="alert" role="alert">
          {error}
        </p>
      )}

      {(loading || showImage || prompt) && (
        <div className="image-body">
          <div className="image-frame">
            {loading ? (
              <div className="image-placeholder shimmer">
                <p>Painting four skies… this usually takes 20–60 seconds.</p>
              </div>
            ) : showImage ? (
              <a href={result.image} download="weather-dashboard.png" title="Download image">
                {/* eslint-disable-next-line @next/next/no-img-element -- data: URL from our API */}
                <img src={result.image} alt="AI-generated picture of the weather in the four locations" />
              </a>
            ) : (
              <div className="image-placeholder">
                <p>No image this time. The prompt that was sent is on the right.</p>
              </div>
            )}
          </div>

          {prompt && !loading && (
            <aside className="prompt">
              <div className="prompt-head">
                <h3>Prompt{showImage ? <span className="dim"> · {result.model}</span> : null}</h3>
                <button className="btn-ghost small" onClick={() => copyPrompt(prompt)}>
                  {copied ? 'Copied' : 'Copy'}
                </button>
              </div>
              <pre>{prompt}</pre>
              {showImage && (
                <a className="btn-ghost small download" href={result.image} download="weather-dashboard.png">
                  Download PNG
                </a>
              )}
            </aside>
          )}
        </div>
      )}
    </section>
  );
}
