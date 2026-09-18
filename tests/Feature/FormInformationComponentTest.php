<?php

use Illuminate\Support\Facades\Blade;

it('renders caller guidance and attributes with or without an icon', function (bool $icon) {
    $html = Blade::render('<x-form-information :icon="$icon" title="Informasi Tutup PO" heading-class="font-semibold" class="mt-3" data-section="confirmation"><p>Pastikan tidak ada sisa penerimaan.</p></x-form-information>', ['icon' => $icon]);

    expect($html)->toContain('Informasi Tutup PO', 'Pastikan tidak ada sisa penerimaan.', 'data-section="confirmation"', 'mt-3', 'font-semibold');
    expect(str_contains($html, '<svg'))->toBe($icon);
})->with([true, false]);

it('escapes a custom information heading', function () {
    $html = Blade::render('<x-form-information :title="$title">Petunjuk</x-form-information>', ['title' => '<script>alert(1)</script>']);

    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;', 'Petunjuk')->not->toContain('<script>');
});
