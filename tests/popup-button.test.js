const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ejs = require('ejs');

const adminRoute = fs.readFileSync(path.join(__dirname, '..', 'routes/admin.js'), 'utf8');
const adminView = fs.readFileSync(path.join(__dirname, '..', 'views/admin.ejs'), 'utf8');
const dashboardView = fs.readFileSync(path.join(__dirname, '..', 'views/index.ejs'), 'utf8');
const themeStyles = fs.readFileSync(path.join(__dirname, '..', 'assets/dangote-theme.css'), 'utf8');

test('admin can configure a popup button name and destination together', () => {
  assert.match(adminView, /id="popupButtonLabel"[^>]*maxlength="60"/);
  assert.match(adminView, /type="url"[^>]*id="popupButtonUrl"/);
  assert.match(adminView, /onclick="savePopupButton\(\)"/);
  assert.match(adminRoute, /\/adminxyz\/parametres\/popup-button\/save/);
  assert.match(adminRoute, /Boolean\(label\) !== Boolean\(url\)/);
  assert.match(adminRoute, /parsedUrl\.protocol/);
  assert.match(adminRoute, /popup_button_label/);
  assert.match(adminRoute, /popup_button_url/);
  assert.doesNotThrow(() => ejs.compile(adminView, { filename: 'views/admin.ejs' }));
});

test('customer welcome popup shows a safely escaped custom link only when both values are set', () => {
  assert.match(dashboardView, /if \(popupButtonLabel && popupButtonUrl\)/);
  assert.match(dashboardView, /href="<%= popupButtonUrl %>"/);
  assert.match(dashboardView, /rel="noopener noreferrer"/);
  assert.match(dashboardView, /<%= popupButtonLabel %>/);
  assert.doesNotThrow(() => ejs.compile(dashboardView, { filename: 'views/index.ejs' }));
});

test('welcome popup keeps its message and actions readable above mobile controls', () => {
  assert.match(dashboardView, /class="welcome-body">\s*<p class="welcome-message">/);
  assert.match(themeStyles, /\.welcome-popup\s*\{[^}]*z-index:\s*20000\s*!important/s);
  assert.match(themeStyles, /\.welcome-popup \.welcome-header h3\s*\{[^}]*color:\s*#fff\s*!important/s);
  assert.match(themeStyles, /\.welcome-popup a\.welcome-btn-custom\s*\{[^}]*color:\s*#fff\s*!important/s);
  assert.match(themeStyles, /\.welcome-popup \.welcome-content\s*\{[^}]*100dvh[^}]*!important/s);
});