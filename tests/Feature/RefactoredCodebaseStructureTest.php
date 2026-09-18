<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

it('resolves every domain action and service through the container without HTTP requests', function () {
    Http::preventStrayRequests();

    foreach (['Actions', 'Services'] as $directory) {
        foreach (File::allFiles(app_path($directory)) as $file) {
            $class = 'App\\'.$directory.'\\'.str_replace('/', '\\', substr($file->getRelativePathname(), 0, -4));

            expect(app($class))->toBeInstanceOf($class);
        }
    }

    Http::assertNothingSent();
});

it('finds every literal custom view referenced by application classes and Blade includes', function () {
    $files = array_merge(File::allFiles(app_path()), File::allFiles(resource_path('views')));
    $checked = [];

    foreach ($files as $file) {
        preg_match_all('/(?:\$view\s*=\s*|(?:view|loadView|View::make|@include|@extends)\(\s*)[\'"](filament\.[^\'"]+)[\'"]/', $file->getContents(), $matches);

        foreach ($matches[1] as $view) {
            expect(View::exists($view))->toBeTrue("Missing view {$view} referenced in {$file->getPathname()}");
            $checked[$view] = true;
        }
    }

    expect(count($checked))->toBeGreaterThan(100);
});
