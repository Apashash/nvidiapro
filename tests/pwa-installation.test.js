const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

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

test('profile page offers a PWA install action and loads its install support', () => {
  const profile = fs.readFileSync(path.join(root, 'views/compte.ejs'), 'utf8');
  const installScript = fs.readFileSync(path.join(root, 'assets/pwa-install.js'), 'utf8');
  assert.match(profile, /rel="manifest" href="\/manifest\.webmanifest"/);
  assert.match(profile, /data-pwa-install-button data-pwa-install-entry/);
  assert.match(profile, /include\('partials\/pwa-install-help'\)/);
  assert.match(profile, /\/assets\/pwa-install\.js\?v=2/);
  assert.match(installScript, /document\.querySelectorAll\('\[data-pwa-install-button\]'\)/);
  assert.match(installScript, /if \(!banner && !installButtons\.length\) return/);
});

test('profile install button opens Android instructions without a timed banner', async () => {
  const installScript = fs.readFileSync(path.join(root, 'assets/pwa-install.js'), 'utf8');
  const listeners = {};
  const buttonListeners = {};
  const installButton = {
    hidden: false,
    addEventListener: (name, callback) => { buttonListeners[name] = callback; },
    focus() { this.focused = true; },
  };
  const helpText = { textContent: '' };
  const helpCloseButton = { focus() { this.focused = true; } };
  const helpDialog = {
    hidden: true,
    querySelector: selector => selector === '[data-pwa-install-help-close]'
      ? helpCloseButton
      : helpText,
    addEventListener() {},
  };
  const document = {
    addEventListener: (name, callback) => { listeners[name] = callback; },
    querySelector: selector => selector === '[data-pwa-install-banner]'
      ? null
      : helpDialog,
    querySelectorAll: selector => selector === '[data-pwa-install-button]'
      ? [installButton]
      : [installButton],
  };
  const window = {
    navigator: { userAgent: 'Mozilla/5.0 (Linux; Android 14)' },
    matchMedia: () => ({ matches: false }),
    addEventListener() {},
    clearTimeout() {},
    setTimeout() { return 1; },
  };

  vm.runInNewContext(installScript, { window, document, navigator: {}, console });
  listeners.DOMContentLoaded();
  await buttonListeners.click({ currentTarget: installButton });

  assert.equal(helpDialog.hidden, false);
  assert.match(helpText.textContent, /Sur Android/);
  assert.equal(helpCloseButton.focused, true);
});