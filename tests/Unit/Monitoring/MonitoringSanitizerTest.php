<?php

use App\Services\Monitoring\MonitoringSanitizer;

beforeEach(function () {
    $this->sanitizer = new MonitoringSanitizer;
});

it('keeps only allowlisted fields of a domain payload', function () {
    $summary = $this->sanitizer->summarize('item_journal', [
        'itemJournalDate' => '2026-10-20',
        'branchID' => 373,
        'additionalInfo' => 'Catatan bebas berisi nomor 081234567890',
        'itemJournalDetails' => [
            ['ID' => -1, 'productDetailID' => 2112, 'purposeID' => 10, 'qty' => -2, 'hpp' => 45000],
        ],
    ]);

    expect($summary)->toBe([
        'itemJournalDate' => '2026-10-20',
        'branchID' => 373,
        'itemJournalDetails' => [['productDetailID' => 2112, 'purposeID' => 10, 'qty' => -2]],
    ]);
});

it('drops every field of an unknown domain', function () {
    expect($this->sanitizer->summarize('unknown_domain', ['password' => 'secret', 'name' => 'x']))->toBe([]);
});

it('redacts secret-like keys recursively', function () {
    $result = $this->sanitizer->sanitize([
        'username' => 'integration',
        'password' => 'p@ss',
        'nested' => ['Authorization' => 'Bearer abc', 'api_key' => 'k', 'cookie' => 'c', 'accessToken' => 't', 'safe' => 1],
    ]);

    expect($result['password'])->toBe(MonitoringSanitizer::REDACTED)
        ->and($result['nested']['Authorization'])->toBe(MonitoringSanitizer::REDACTED)
        ->and($result['nested']['api_key'])->toBe(MonitoringSanitizer::REDACTED)
        ->and($result['nested']['cookie'])->toBe(MonitoringSanitizer::REDACTED)
        ->and($result['nested']['accessToken'])->toBe(MonitoringSanitizer::REDACTED)
        ->and($result['nested']['safe'])->toBe(1)
        ->and(json_encode($result))->not->toContain('p@ss')->not->toContain('Bearer abc');
});

it('replaces binary and base64 content with a size marker', function () {
    $result = $this->sanitizer->sanitize([
        'file' => base64_encode(random_bytes(300)),
        'raw' => "\x00\x01\x02binary",
        'inline' => 'data:image/png;base64,iVBORw0KGgo=',
    ]);

    expect($result['file'])->toStartWith('[BINARY')
        ->and($result['raw'])->toStartWith('[BINARY')
        ->and($result['inline'])->toStartWith('[BINARY');
});

it('strips signed URL query strings and credentials', function () {
    $url = 'https://user:pass@files.example.com/qc/photo.jpg?X-Amz-Signature=abc&X-Amz-Credential=key';

    expect($this->sanitizer->sanitize(['url' => $url])['url'])->toBe('https://files.example.com/qc/photo.jpg')
        ->and($this->sanitizer->message("Upload gagal untuk {$url}"))->toBe('Upload gagal untuk https://files.example.com/qc/photo.jpg');
});

it('masks e-mail addresses, phone numbers, and bearer tokens in messages', function () {
    $message = $this->sanitizer->message('User budi.santoso@bloomery.test (081234567890) gagal: Bearer eyJhbGciOi.secret password=hunter2');

    expect($message)->toContain('b***@bloomery.test')
        ->not->toContain('budi.santoso')
        ->not->toContain('081234567890')
        ->not->toContain('eyJhbGciOi')
        ->not->toContain('hunter2');
});

it('limits depth, list size, and string length deterministically', function () {
    $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'too deep']]]]]];
    $list = range(1, 40);
    $long = str_repeat('x ', 400);

    $first = $this->sanitizer->sanitize(compact('deep', 'list', 'long'));
    $second = $this->sanitizer->sanitize(compact('deep', 'list', 'long'));

    expect($first)->toBe($second)
        ->and(data_get($first, 'deep.a.b.c.d'))->toBe('[MAX DEPTH]')
        ->and($first['list'])->toHaveKey('_truncated', 15)
        ->and(mb_strlen($first['long']))->toBeLessThanOrEqual(300);
});
