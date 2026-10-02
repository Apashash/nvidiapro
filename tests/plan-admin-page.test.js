const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ejs = require('ejs');

const adminRoute = fs.readFileSync(path.join(__dirname, '..', 'routes/admin.js'), 'utf8');
const adminView = fs.readFileSync(path.join(__dirname, '..', 'views/admin.ejs'), 'utf8');

test('adding a plan opens a dedicated page with the shared plan editor', () => {
  assert.match(adminRoute, /router\.get\('\/adminxyz\/plans\/new'/);
  assert.match(adminRoute, /router\.post\('\/adminxyz\/plans\/new'/);
  assert.match(adminView, /<a class="btn-add" href="\/adminxyz\/plans\/new">/);
  assert.match(adminView, /currentPage === 'plan-edit' \|\| currentPage === 'plan-new'/);
  assert.doesNotMatch(adminView, /id="addPlanModal"/);
  assert.doesNotMatch(adminView, /openAddPlanModal/);
  assert.doesNotThrow(() => ejs.compile(adminView, { filename: 'views/admin.ejs' }));
});

test('the admin plan list shows disabled plans with a clear reactivation action', () => {
  assert.match(
    adminRoute,
    /router\.get\('\/adminxyz\/plans'[\s\S]*?SELECT \* FROM planinvestissement ORDER BY id ASC/
  );
  assert.match(adminView, /Désactivé · touchez le cadenas pour réactiver/);
  assert.match(adminView, /title="<%= p\.bloque \? 'Réactiver ce plan' : 'Désactiver ce plan' %>"/);
  assert.match(adminView, /data-bloque="<%= p\.bloque \? 'true' : 'false' %>"/);
});