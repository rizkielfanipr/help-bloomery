import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/filament/casual/pages/technician-request-page.blade.php', import.meta.url), 'utf8');
const script = template.match(/@script\s*<script>([\s\S]*?)<\/script>/)[1];

function scanner(getUserMedia, options = {}) {
    let factory;
    let stopped = 0;
    let released = 0;
    const navigations = [];
    const stream = { getTracks: () => [{ stop: () => stopped++ }] };
    class Detector {
        async detect() { return options.codes ?? []; }
    }
    vm.runInNewContext(script, {
        Alpine: { data: (name, value) => { factory = value; } },
        window: { BarcodeDetector: Detector, location: { assign: (url) => navigations.push(url) } },
        createImageBitmap: options.createImageBitmap ?? (async () => ({ close: () => released++ })),
        BarcodeDetector: Detector,
        navigator: { mediaDevices: { getUserMedia: getUserMedia ?? (async () => stream) } },
        requestAnimationFrame: () => 1,
        cancelAnimationFrame: () => {},
        URL,
    });
    const state = factory();
    state.$refs = { scannerVideo: { play: async () => {} } };
    state.$nextTick = async (callback) => callback?.();
    return { state, stream, stopped: () => stopped, navigations, released: () => released };
}

const flush = () => new Promise((resolve) => setImmediate(resolve));

test('opening the QR tab starts the camera without another click', async () => {
    const { state, stopped } = scanner();
    state.selectMode('qr');
    await flush();
    assert.equal(state.scanning, true);
    assert.equal(state.starting, false);
    state.selectMode('manual');
    assert.equal(state.scanning, false);
    assert.equal(stopped(), 1);
});

test('leaving the QR tab during camera permission does not leave a stream running', async () => {
    let resolveCamera;
    const { state, stream, stopped } = scanner(() => new Promise((resolve) => { resolveCamera = resolve; }));
    state.selectMode('qr');
    state.selectMode('manual');
    resolveCamera(stream);
    await flush();
    assert.equal(state.scanning, false);
    assert.equal(state.stream, null);
    assert.equal(stopped(), 1);
});

test('camera permission failure allows retry and destroying the scanner releases the camera', async () => {
    const { state } = scanner(async () => { throw new Error('Permission denied'); });
    state.selectMode('qr');
    await flush();
    assert.equal(state.starting, false);
    assert.equal(state.scanning, false);
    assert.match(state.error, /izin kamera/);
    const active = scanner();
    active.state.selectMode('qr');
    await flush();
    active.state.destroy();
    assert.equal(active.stopped(), 1);
});

const imageEvent = (type = 'image/png', size = 1024) => ({ target: { files: [{ type, size }], value: 'qr.png' } });
const assetToken = '12345678-1234-1234-1234-123456789abc';

test('an uploaded QR image opens the asset locally and releases its decoded image', async () => {
    const current = scanner(undefined, { codes: [{ rawValue: `https://casual.help-bloomery.test/assets/scan/${assetToken}` }] });
    current.state.mode = 'qr';
    await current.state.readQrImage(imageEvent());
    assert.deepEqual(current.navigations, [`/assets/scan/${assetToken}`]);
    assert.equal(current.released(), 1);
    assert.equal(current.state.readingImage, false);
});

test('invalid and oversized files are rejected before decoding', async () => {
    const current = scanner();
    current.state.mode = 'qr';
    await current.state.readQrImage(imageEvent('application/pdf'));
    assert.match(current.state.error, /maksimal 5 MB/);
    await current.state.readQrImage(imageEvent('image/png', 6 * 1024 * 1024));
    assert.match(current.state.error, /maksimal 5 MB/);
    assert.equal(current.released(), 0);
});

test('an image without an asset QR shows an error instead of navigating', async () => {
    const current = scanner(undefined, { codes: [{ rawValue: 'https://example.com/' }] });
    current.state.mode = 'qr';
    await current.state.readQrImage(imageEvent());
    assert.match(current.state.error, /QR asset tidak ditemukan/);
    assert.equal(current.navigations.length, 0);
    assert.equal(current.released(), 1);
});

test('switching to manual during image decoding prevents late navigation', async () => {
    let resolveImage;
    let released = false;
    const current = scanner(undefined, { codes: [{ rawValue: assetToken }], createImageBitmap: () => new Promise((resolve) => { resolveImage = resolve; }) });
    current.state.mode = 'qr';
    const reading = current.state.readQrImage(imageEvent());
    current.state.selectMode('manual');
    resolveImage({ close: () => { released = true; } });
    await reading;
    assert.equal(current.navigations.length, 0);
    assert.equal(released, true);
    assert.equal(current.state.readingImage, false);
});
