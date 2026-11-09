/**
 * Browser test for the deactivation survey. Run with `npm run test:browser`.
 */
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( 'playwright' );

const root = path.resolve( __dirname, '../..' );
const js = fs.readFileSync(root + '/src/Feedback/assets/deactivation-survey.js', 'utf8');
const css = fs.readFileSync(root + '/src/Feedback/assets/deactivation-survey.css', 'utf8');
const config = {
  basename: 'acme-bookings/acme-bookings.php', ajaxUrl: 'https://site.test/wp-admin/admin-ajax.php',
  action: 'acme_bookings_deactivation_feedback', nonce: 'n1', maxLength: 1000, askConsent: true,
  reasons: [
    { id: 'temporary', label: "It's temporary", detailsPrompt: null },
    { id: 'found_better', label: 'I found a better plugin', detailsPrompt: 'Which plugin?' },
  ],
  strings: { title: 'Quick question', intro: 'Why?', privacy: 'Sent to developer.', consent: 'Include my site address',
    submit: 'Submit & deactivate', skip: 'Skip & deactivate', cancel: 'Cancel', required: 'Please choose a reason, or skip.' },
};
const html = `<!doctype html><html><head><style>${css}</style></head><body>
<table><tr data-plugin="acme-bookings/acme-bookings.php"><td><span class="deactivate"><a href="https://site.test/wp-admin/plugins.php?action=deactivate">Deactivate</a></span></td></tr>
<tr data-plugin="other/other.php"><td><span class="deactivate"><a id="other" href="https://site.test/other">Deactivate</a></span></td></tr></table>
<script>${js}(${JSON.stringify(config)});</script></body></html>`;

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1000, height: 800 }, deviceScaleFactor: 1 });
  let posted = null;
  await page.route('https://site.test/**', async (route) => {
    const req = route.request();
    if (req.method() === 'POST') { posted = req.postData(); return route.fulfill({ status: 200, body: '{"success":true}', contentType: 'application/json' }); }
    return route.fulfill({ status: 200, body: '<p>deactivated ' + req.url() + '</p>', contentType: 'text/html' });
  });
  await page.route('https://site.test/wp-admin/plugins.php', (r) => r.fulfill({ status: 200, body: html, contentType: 'text/html' }));
  await page.goto('https://site.test/wp-admin/plugins.php');

  await page.click('tr[data-plugin="acme-bookings/acme-bookings.php"] .deactivate a');
  assert.equal( await page.$eval( 'dialog.wpf-survey', ( d ) => d.open ), true, 'dialog opens' );

  // Submitting with no reason shows the error and stays on the page.
  await page.click('button[type=submit]');
  assert.equal( await page.isVisible( '.wpf-survey__error' ), true, 'a reason is required' );
  assert.equal( page.url(), 'https://site.test/wp-admin/plugins.php' );

  await page.check('input[value=found_better]');
  assert.equal( await page.isVisible( 'textarea[name=details-found_better]' ), true, 'details field shows' );
  await page.fill('textarea[name=details-found_better]', 'Better Bookings');
  await page.check('input[name=consent]');

  await Promise.all([page.waitForURL('**/plugins.php?action=deactivate'), page.click('button[type=submit]')]);
  for ( const [ field, value ] of [ [ 'action', config.action ], [ 'nonce', 'n1' ], [ 'reason', 'found_better' ], [ 'details', 'Better Bookings' ], [ 'consent', '1' ] ] ) {
    assert.match( posted, new RegExp( `name="${ field }"\\r\\n\\r\\n${ value }\\r\\n` ), `posts ${ field }` );
  }

  // Cancel closes; Skip deactivates without posting.
  await page.goto('https://site.test/wp-admin/plugins.php');
  posted = null;
  await page.click('tr[data-plugin="acme-bookings/acme-bookings.php"] .deactivate a');
  await page.click('[data-wpf=cancel]');
  assert.equal( await page.$eval( 'dialog.wpf-survey', ( d ) => d.open ), false, 'cancel closes' );
  await page.click('tr[data-plugin="acme-bookings/acme-bookings.php"] .deactivate a');
  await Promise.all([page.waitForURL('**/plugins.php?action=deactivate'), page.click('[data-wpf=skip]')]);
  assert.equal( posted, null, 'skip sends nothing' );

  // Other plugins' links are untouched.
  await page.goto('https://site.test/wp-admin/plugins.php');
  await Promise.all([page.waitForURL('**/other'), page.click('#other')]);
  assert.equal( page.url(), 'https://site.test/other', 'other plugins are unaffected' );
  await browser.close();
  console.log( 'Deactivation survey: all browser checks passed.' );
})().catch((e) => { console.error(e); process.exit(1); });
