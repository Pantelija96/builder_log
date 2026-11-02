<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Static files are served by the web server. Only /app browser navigations
// reach this route; a missing asset must remain a 404, not receive SPA HTML.
Route::get('/app/{path?}', function (?string $path = null) {
    abort_if($path !== null && (
        $path === 'assets' || str_starts_with($path, 'assets/')
        || preg_match('~(?:^|/)[^/]*\.[^/]*$~', $path)
    ), 404);

    $entry = public_path('app/index.html');
    abort_unless(is_file($entry), 503, 'PWA build is not available.');

    return response()->file($entry, ['Cache-Control' => 'no-cache']);
})->where('path', '.*')->name('pwa');
