import type { Scene } from '@/lib/scene';

type IconProps = { size?: number; className?: string };

const base = (size: number) => ({
  width: size,
  height: size,
  viewBox: '0 0 64 64',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 3,
  strokeLinecap: 'round' as const,
  strokeLinejoin: 'round' as const,
  'aria-hidden': true,
});

const cloud = 'M20 46h26a10 10 0 0 0 0-20 14 14 0 0 0-27-2A11 11 0 0 0 20 46Z';

const sun = (cx: number, cy: number, r: number) => (
  <g>
    <circle cx={cx} cy={cy} r={r} />
    {[0, 45, 90, 135, 180, 225, 270, 315].map((a) => {
      const rad = (a * Math.PI) / 180;
      return (
        <line
          key={a}
          x1={cx + Math.cos(rad) * (r + 5)}
          y1={cy + Math.sin(rad) * (r + 5)}
          x2={cx + Math.cos(rad) * (r + 10)}
          y2={cy + Math.sin(rad) * (r + 10)}
        />
      );
    })}
  </g>
);

const moon = (dx = 0, dy = 0) => <path d={`M${40 + dx} ${14 + dy}a17 17 0 1 0 12 26 14 14 0 0 1-12-26Z`} />;

/** Large weather glyph for a card, drawn with currentColor. */
export function WeatherIcon({ scene, size = 72, className }: IconProps & { scene: Scene }) {
  return (
    <svg {...base(size)} className={className}>
      {scene === 'clear-day' && sun(32, 32, 11)}
      {scene === 'clear-night' && moon(-6, 2)}
      {scene === 'partly-day' && (
        <>
          <g transform="translate(-8 -8) scale(0.8)">{sun(40, 30, 9)}</g>
          <path d={cloud} />
        </>
      )}
      {scene === 'partly-night' && (
        <>
          <g transform="translate(4 -6) scale(0.7)">{moon()}</g>
          <path d={cloud} />
        </>
      )}
      {scene === 'cloudy' && (
        <>
          <path d="M30 20a12 12 0 0 1 21 6" opacity={0.6} />
          <path d={cloud} />
        </>
      )}
      {scene === 'fog' && (
        <>
          <path d="M12 26h40M8 34h44M14 42h36M20 50h24" />
        </>
      )}
      {scene === 'rain' && (
        <>
          <path d="M20 38h26a10 10 0 0 0 0-20 14 14 0 0 0-27-2A11 11 0 0 0 20 38Z" />
          <path d="M22 46l-3 8M33 46l-3 8M44 46l-3 8" />
        </>
      )}
      {scene === 'snow' && (
        <>
          <path d="M20 38h26a10 10 0 0 0 0-20 14 14 0 0 0-27-2A11 11 0 0 0 20 38Z" />
          {[22, 33, 44].map((x, i) => (
            <g key={x} transform={`translate(${x} ${50 + (i % 2) * 4})`}>
              <path d="M0 -4v8M-3.5 -2l7 4M-3.5 2l7 -4" strokeWidth={2.2} />
            </g>
          ))}
        </>
      )}
      {scene === 'storm' && (
        <>
          <path d="M20 38h26a10 10 0 0 0 0-20 14 14 0 0 0-27-2A11 11 0 0 0 20 38Z" />
          <path d="M34 40l-6 10h8l-5 10" />
        </>
      )}
    </svg>
  );
}

export function WindIcon({ size = 16 }: IconProps) {
  return (
    <svg {...base(size)} strokeWidth={5}>
      <path d="M6 24h34a8 8 0 1 0-8-8M6 36h44a8 8 0 1 1-8 8M6 48h22" />
    </svg>
  );
}

export function ClockIcon({ size = 14 }: IconProps) {
  return (
    <svg {...base(size)} strokeWidth={5}>
      <circle cx="32" cy="32" r="24" />
      <path d="M32 18v14l9 6" />
    </svg>
  );
}

export function PlusIcon({ size = 28 }: IconProps) {
  return (
    <svg {...base(size)} strokeWidth={5}>
      <path d="M32 14v36M14 32h36" />
    </svg>
  );
}

export function SparkleIcon({ size = 18 }: IconProps) {
  return (
    <svg {...base(size)} strokeWidth={4}>
      <path d="M32 8l5 15 15 5-15 5-5 15-5-15-15-5 15-5Z" />
      <path d="M50 44l2 6 6 2-6 2-2 6-2-6-6-2 6-2Z" />
    </svg>
  );
}
