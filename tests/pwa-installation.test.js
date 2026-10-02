const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/manifest.webmanifest'), 'utf8'));

test('PWA manifest has standalone launch settings and a root scope', () => {
  assert.equal(manifest.start_url, '/');
  assert.equal(manifest.scope, '/');
  assert.equal(manifest.display, 'standalone');
  assert.deepEqual(manifest.icons.map(icon => icon.sizes), ['192x192', '512x512']);
});

test('PWA manifest icons exist as square PNGs at their declared sizes', () => {
  for (const icon of manifest.icons) {
    const iconPath = path.join(root, 'public', icon.src.replace(/^\//, ''));
    const bytes = fs.readFileSync(iconPath);
    assert.equal(bytes.subarray(1, 4).toString(), 'PNG');
    assert.equal(bytes.readUInt32BE(16), Number.parseInt(icon.sizes, 10));
    assert.equal(bytes.readUInt32BE(20), Number.parseInt(icon.sizes, 10));
  }
});

test('PWA worker is available and does not cache authenticated pages', () => {
  const worker = fs.readFileSync(path.join(root, 'public/sw.js'), 'utf8');
  assert.match(worker, /self\.clients\.claim/);
  assert.match(worker, /event\.request\.method !== 'GET'/);
  assert.match(worker, /event\.respondWith\(fetch\(event\.request\)\)/);
  assert.doesNotMatch(worker, /caches\.open/);
});