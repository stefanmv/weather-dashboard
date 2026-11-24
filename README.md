# Weather dashboard with AI images

Add four places, see the current weather in each, and turn that weather into one AI image.

It is one Laravel 13 project. The UI is a Next.js 15 app (App Router, TypeScript) that replaces Blade:

- **Laravel** (`app/`, `routes/`, SQLite) provides the JSON API under `/api`. It calls Open-Meteo and OpenAI and holds the API key.
- **Next.js** (`frontend/`) is exported as static files into `public/spa` by `npm run build`. Laravel serves `public/spa/index.html` at `/` (`routes/web.php`), so the page and the API share one origin and one server.

## Run it from a fresh clone

You need PHP 8.3+, Composer 2, and Node 20+ (`.nvmrc` pins 20; run `nvm use` if you use nvm).

```bash
composer run setup
```

That one command runs `composer install`, creates `.env`, generates the app key, creates the SQLite database, runs the migrations, installs the npm packages (root and `frontend/`), and builds the frontend into `public/spa`.

Open `.env` and set `OPENAI_API_KEY=sk-...`, then:

```bash
php artisan serve
```

Open http://localhost:8000.

**Tests:** `php artisan test`. These are feature tests that fake every external HTTP call, so they need no key and no network.

**Frontend with hot reload (optional):** `npm run dev` starts Laravel on :8000 and `next dev` on :3000. Open http://localhost:3000; it calls the API on :8000 (`frontend/.env.development`, CORS via `FRONTEND_URL`). Run `npm run build` again to update what :8000 serves.

## How it works

| Requirement | Where |
|---|---|
| Search, pick the right Springfield | `GET /api/geocode` returns up to 10 matches with region, country and coordinates. `frontend/components/PlaceSearch.tsx` |
| Four locations that survive a reload | Stored in SQLite (`locations` table, `slot` 1–4). `LocationController` enforces at most 4 and no duplicates |
| Remove and replace | **Remove** empties a slot. **Change** (`PUT /api/locations/{id}`) swaps in a new place in one step, keeping its slot |
| Weather per location | `GET /api/locations/{id}/weather` → `app/Services/OpenMeteo.php`. Shows °C, km/h, today's high/low, condition (WMO code mapped in `app/Support/WeatherCode.php`), and local time in the place's timezone. Cached 10 min |
| Image prompt built in code | `app/Services/ImagePromptBuilder.php`: a 2×2 grid, one panel per location in slot order. Sky comes from the WMO code, clothing from the temperature, motion from the wind speed, light from the local hour. No LLM is involved |
| Image | `POST /api/image` → `app/Services/ImageGenerator.php` (OpenAI Images API). The prompt is shown next to the image |
| Failures | Each weather card fetches on its own and shows its own error with a Retry button. Unknown place → "No place found". Weather API down or slow → 502/504 with a readable message. Image timeout, refusal, bad key, rate limit or missing key → specific message, and the prompt is still shown. See `tests/Feature/WeatherDashboardTest.php` |

The browser never sees the OpenAI key. The frontend sends a bare `POST /api/image`, and the server loads the saved locations, fetches the weather, builds the prompt and calls OpenAI.

## Choices

- **Weather: [Open-Meteo](https://open-meteo.com).** No key, geocoding included, and it returns local timezone and units (°C, km/h) on request. Reviewers need nothing extra to run it.
- **Image: OpenAI `gpt-image-2`.** It follows long, structured prompts well (it keeps the four panels in order) and is easy to get a key for. It returns base64, so there are no image URLs that expire. One image takes about 30–60 s at medium quality. Model and quality can be changed in `.env` (`OPENAI_IMAGE_MODEL`, `OPENAI_IMAGE_QUALITY`). I first used `gpt-image-1`, but OpenAI no longer serves it, and the API answered "model does not exist".
- **Laravel + Next.js in one project.** Laravel keeps secrets and third-party calls on the server, and SQLite means no database setup. Next.js replaces Blade as the UI. I export it statically because the page needs no server rendering, so reviewers run a single `php artisan serve`, and there is no Node server or CORS in normal use.

## What doesn't work yet / with one more day

- Generation is synchronous: the request stays open for about 20–60 s. With a day more I'd queue it as a Laravel job and have the frontend poll, so a slow model can't hit a PHP or proxy timeout.
- Images aren't saved. A reload loses the last image. I'd store them on disk with the prompt and weather snapshot and show a small history.
- The weather in a card is fetched once on load (plus Retry). It doesn't refresh on its own.
- There are no user accounts: one global list of four locations, as the task allows.
- There are no frontend tests. I'd add a Playwright test for add → reload → remove → generate with the API mocked.
- Place search is submit-to-search, not type-ahead.
- The image model sometimes still draws text or mixes up panel order. The prompt asks it not to, but that isn't guaranteed.

## How I used AI

**Tool:** Claude Code (Claude Opus) in the Claude desktop app. It wrote the code, ran the tests, and used the app's built-in browser to click through the dashboard.

**What I handed it**
- The full task description.
- My decisions:
  - OpenAI for images.
  - Laravel + Next.js for the stack.
  - It writes the code, but I own git. It doesn't create the repo or make commits; I commit myself.
- It proposed a plan first: the endpoints, one weather request per location so one failure stays in its card, and the prompt built on the server from Open-Meteo data. I approved it before any code was written.

**What it produced**
- The Laravel API: geocoding, locations, weather, image.
- The WMO-code map and the prompt builder.
- The Next.js UI.
- The feature tests. They fake Open-Meteo and OpenAI and cover a place that doesn't exist, the weather API down or timing out, and the image model refusing, timing out, or missing its key.
- It checked the result in the browser against the live Open-Meteo API, including a fake weather outage and an unknown place name.

**What I changed or threw away**
- **Two apps → one project.** The first version was two separate apps (`backend/` and `frontend/`), with two servers, two setup procedures and CORS between them. I had it combine them into a single Laravel project where Next.js replaces Blade. Next.js is exported as static files into `public/spa`, and Laravel serves them. Now it's one `composer run setup` and one `php artisan serve`. The Blade/Vite/Tailwind skeleton was deleted.
- **Image model.** The plan used `gpt-image-1`. When I ran it with my own key, OpenAI answered "model does not exist", because that model is no longer offered. We listed the models my key can use, switched to `gpt-image-2`, and kept the model configurable in `.env`.
- **Replacing a location.** With all four slots full, the only way to add a new place was to remove one first. I asked for a better flow, so each card got a **Change** button (`PUT /api/locations/{id}`) that swaps the place in one step. A new place also goes into the slot you clicked.
- **Design.** The first UI was plain. I asked for a better design:
  - card backgrounds and icons that follow the weather and day/night
  - country flags to tell same-named places apart
  - placeholders while things load
  - an image/prompt layout with Copy and Download
  - dark mode and a phone layout
- **Prompt wording.** In the browser check, a night-time panel said "sun breaking through" for a partly cloudy sky. The weather phrases were changed so they don't assume daylight.
