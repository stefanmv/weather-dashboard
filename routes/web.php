<?php

use Illuminate\Support\Facades\Route;

// The UI is a Next.js app (frontend/), exported as static files into
// public/spa by `npm run build`. Laravel serves its index.html instead of a
// Blade view; the JS/CSS under /spa/_next are served straight from public/.
Route::get('/', function () {
    $index = public_path('spa/index.html');

    abort_unless(
        file_exists($index),
        503,
        'The frontend has not been built yet. Run `npm install && npm run build`, then reload.',
    );

    return response()->file($index, ['Content-Type' => 'text/html; charset=UTF-8']);
});
