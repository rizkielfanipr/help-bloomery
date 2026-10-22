<?php

test('service worker leaves page navigation to the browser', function () {
    $serviceWorker = file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain("const CACHE = 'bloomery-v3';")
        ->toContain('if (!isStaticAsset) return;')
        ->not->toContain('isPageRequest')
        ->not->toContain('Koneksi ke server terputus');
});
