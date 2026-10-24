// Keyboard tests for the client-side staff list pagination (public/assets/js/app.js).
// Run:  cd tests/js && npm install && npm test
//
// jsdom does not turn Enter/Space into clicks, so press() reproduces what browsers do:
//   Enter  -> click on keydown (and again for every auto-repeat keydown while held)
//   Space  -> click on keyup
// Browsers also never activate a native `disabled` button, and they drop focus from one that
// becomes disabled; the assertions below therefore require that a focused pagination button is
// still connected and not natively disabled after every keypress.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { JSDOM } from 'jsdom';

const here = dirname(fileURLToPath(import.meta.url));
const source = readFileSync(join(here, '../../public/assets/js/app.js'), 'utf8');
const ROWS = 95; // 10 per page -> 10 pages

function setup(rowCount = ROWS) {
  const rows = Array.from({ length: rowCount }, (_, i) =>
    `<div data-staff-row data-list-search="item ${i + 1}" data-list-name="Item ${i + 1}" data-list-status="active" data-list-quantity="${i}">Item ${i + 1}</div>`).join('');
  const dom = new JSDOM(`<!doctype html><body>
    <div data-staff-list data-list-label="products">
      <div data-staff-tools hidden>
        <input type="search" data-staff-search>
        <select data-staff-status><option value="">All</option><option value="active">Active</option></select>
        <select data-staff-sort><option value="original">Default</option><option value="name">Name</option><option value="quantity">Qty</option></select>
        <button type="button" data-staff-reset>Clear</button>
      </div>
      <div data-staff-tools hidden><p data-staff-count></p>
        <nav><span data-staff-numbers></span><span data-staff-page></span></nav>
      </div>
      <p data-staff-empty hidden></p>
      <div id="rows">${rows}</div>
    </div></body>`, { runScripts: 'outside-only', pretendToBeVisual: true });
  const { window } = dom;
  window.eval(source);
  window.setupStaffInterface();
  const doc = window.document;
  const api = {
    window, doc,
    get previous() { return doc.querySelector('[data-staff-numbers] > button:first-child'); },
    get next() { return doc.querySelector('[data-staff-numbers] > button:last-child'); },
    number: (n) => doc.querySelector(`[data-staff-goto="${n}"]`),
    current: () => doc.querySelector('[data-staff-page-list] [aria-current="page"]'),
    page: () => Number(/Page (\d+) of/.exec(doc.querySelector('[data-staff-page]').textContent)[1]),
    visibleRows: () => [...doc.querySelectorAll('[data-staff-row]')].filter((r) => !r.hidden).length,
  };
  // Browser-like activation. Returns nothing; throws if focus is lost or lands on a dead element.
  api.press = (el, key, { repeat = 1 } = {}) => {
    for (let i = 0; i < repeat; i++) {
      const ev = (type, extra = {}) => new window.KeyboardEvent(type, { key, bubbles: true, cancelable: true, ...extra });
      const activate = () => { if (!el.disabled) el.click(); };
      if (key === 'Enter') {
        if (el.dispatchEvent(ev('keydown', { repeat: i > 0 }))) activate();
        el.dispatchEvent(ev('keyup'));
      } else if (key === ' ') {
        el.dispatchEvent(ev('keydown', { repeat: i > 0 }));
        if (el.dispatchEvent(ev('keyup'))) activate();
      } else throw new Error(`unsupported key ${key}`);
    }
  };
  api.assertFocusAlive = () => {
    const a = doc.activeElement;
    assert.ok(a && a !== doc.body, 'focus must not fall back to <body>');
    assert.ok(a.isConnected, 'focused element must still be in the document');
    assert.equal(a.disabled, false, 'focused pagination button must not be natively disabled');
  };
  return api;
}

test('initial state: page 1 of 10, Previous is aria-disabled and nothing is natively disabled', () => {
  const t = setup();
  assert.equal(t.page(), 1);
  assert.equal(t.visibleRows(), 10);
  assert.equal(t.previous.getAttribute('aria-disabled'), 'true');
  assert.equal(t.next.getAttribute('aria-disabled'), 'false');
  assert.equal(t.doc.querySelectorAll('[data-staff-numbers] button[disabled]').length, 0);
  assert.equal(t.current().textContent, '1');
});

test('repeated Enter on Next advances one page per press and keeps focus on the same Next button', () => {
  const t = setup();
  const next = t.next;
  next.focus();
  for (let expected = 2; expected <= 5; expected++) {
    t.press(next, 'Enter');
    assert.equal(t.page(), expected);
    assert.equal(t.doc.activeElement, next);
    assert.equal(t.next, next, 'Next button must not be rebuilt');
    t.assertFocusAlive();
  }
});

test('held Enter (auto-repeat) on Next keeps paging and focus', () => {
  const t = setup();
  t.next.focus();
  t.press(t.next, 'Enter', { repeat: 4 });
  assert.equal(t.page(), 5);
  assert.equal(t.doc.activeElement, t.next);
  t.assertFocusAlive();
});

test('repeated Space on Next advances and keeps focus', () => {
  const t = setup();
  t.next.focus();
  t.press(t.next, ' ', { repeat: 3 });
  assert.equal(t.page(), 4);
  assert.equal(t.doc.activeElement, t.next);
  t.assertFocusAlive();
});

test('repeated Enter and Space on Previous step backwards and keep focus', () => {
  const t = setup();
  t.next.focus();
  t.press(t.next, 'Enter', { repeat: 5 });
  assert.equal(t.page(), 6);
  t.previous.focus();
  t.press(t.previous, 'Enter', { repeat: 2 });
  assert.equal(t.page(), 4);
  t.press(t.previous, ' ', { repeat: 2 });
  assert.equal(t.page(), 2);
  assert.equal(t.doc.activeElement, t.previous);
  t.assertFocusAlive();
});

test('Enter on a numbered page jumps there and focus moves to that page\'s (rebuilt) button', () => {
  const t = setup();
  const old = t.number(3);
  old.focus();
  t.press(old, 'Enter');
  assert.equal(t.page(), 3);
  assert.ok(!old.isConnected, 'numbered buttons are rebuilt');
  const now = t.current();
  assert.equal(now.textContent, '3');
  assert.equal(t.doc.activeElement, now);
  t.assertFocusAlive();
  assert.equal(t.visibleRows(), 10);
});

test('Space on numbered pages works repeatedly, hopping between neighbours', () => {
  const t = setup();
  for (const n of [2, 4, 6, 8, 10]) {
    const button = t.number(n);
    assert.ok(button, `page ${n} button should be reachable from the current window`);
    button.focus();
    t.press(button, ' ');
    assert.equal(t.page(), n);
    assert.equal(t.doc.activeElement, t.current());
    assert.equal(t.current().textContent, String(n));
    t.assertFocusAlive();
  }
});

test('repeated Enter/Space on the current page number is a no-op and keeps focus', () => {
  const t = setup();
  t.next.focus();
  t.press(t.next, 'Enter', { repeat: 3 }); // -> page 4
  const cur = t.current();
  cur.focus();
  t.press(cur, 'Enter', { repeat: 3 });
  t.press(cur, ' ', { repeat: 3 });
  assert.equal(t.page(), 4);
  assert.equal(t.doc.activeElement, cur);
  assert.ok(cur.isConnected);
  t.assertFocusAlive();
});

test('number buttons show first, last and a ±2 window with ellipses', () => {
  const t = setup();
  t.next.focus();
  t.press(t.next, 'Enter', { repeat: 5 }); // page 6
  const labels = [...t.doc.querySelectorAll('[data-staff-page-list] > *')].map((n) => n.textContent);
  assert.deepEqual(labels, ['1', '…', '4', '5', '6', '7', '8', '…', '10']);
});

test('first-page boundary: Previous is inert, stays focused and repeated Enter/Space do nothing', () => {
  const t = setup();
  const prev = t.previous;
  prev.focus();
  t.press(prev, 'Enter', { repeat: 3 });
  t.press(prev, ' ', { repeat: 3 });
  assert.equal(t.page(), 1);
  assert.equal(t.doc.activeElement, prev);
  assert.equal(prev.getAttribute('aria-disabled'), 'true');
  t.assertFocusAlive();
});

test('crossing into the first page with Previous keeps focus on Previous (the original regression)', () => {
  const t = setup();
  t.next.focus();
  t.press(t.next, 'Enter'); // page 2
  const prev = t.previous;
  prev.focus();
  t.press(prev, 'Enter'); // page 1: Previous becomes aria-disabled while focused
  assert.equal(t.page(), 1);
  assert.equal(prev.getAttribute('aria-disabled'), 'true');
  assert.equal(t.doc.activeElement, prev);
  t.assertFocusAlive();
  t.press(prev, 'Enter', { repeat: 2 });
  assert.equal(t.page(), 1);
  assert.equal(t.doc.activeElement, prev);
});

test('last-page boundary: Next crosses into page 10, then stays focused and inert', () => {
  const t = setup();
  const lastButton = t.number(10);
  lastButton.focus();
  t.press(lastButton, 'Enter');
  assert.equal(t.page(), 10);
  assert.equal(t.visibleRows(), 5);
  const next = t.next;
  assert.equal(next.getAttribute('aria-disabled'), 'true');
  next.focus();
  t.press(next, 'Enter', { repeat: 3 });
  t.press(next, ' ', { repeat: 3 });
  assert.equal(t.page(), 10);
  assert.equal(t.doc.activeElement, next);
  t.assertFocusAlive();
});

test('crossing into the last page with Next keeps focus on Next', () => {
  const t = setup();
  // Reach page 9 via repeated Next so the test does not depend on the visible window.
  t.next.focus();
  t.press(t.next, 'Enter', { repeat: 8 });
  assert.equal(t.page(), 9);
  const next = t.next;
  t.press(next, 'Enter'); // page 10: Next becomes aria-disabled while focused
  assert.equal(t.page(), 10);
  assert.equal(next.getAttribute('aria-disabled'), 'true');
  assert.equal(t.doc.activeElement, next);
  t.assertFocusAlive();
});

test('first and last number buttons are always available and land on the boundary pages', () => {
  const t = setup();
  t.next.focus();
  t.press(t.next, 'Enter', { repeat: 5 }); // page 6; "1" and "10" are outside the ±2 window but present
  const first = t.number(1);
  first.focus();
  t.press(first, 'Enter');
  assert.equal(t.page(), 1);
  assert.equal(t.previous.getAttribute('aria-disabled'), 'true');
  assert.equal(t.doc.activeElement, t.current());
  const last = t.number(10);
  last.focus();
  t.press(last, ' ');
  assert.equal(t.page(), 10);
  assert.equal(t.next.getAttribute('aria-disabled'), 'true');
  assert.equal(t.doc.activeElement, t.current());
  t.assertFocusAlive();
});

test('filtering to a single page hides the pager and keeps focus in the search box', () => {
  const t = setup();
  const search = t.doc.querySelector('[data-staff-search]');
  search.focus();
  search.value = 'item 95'; // exactly one match
  search.dispatchEvent(new t.window.Event('input', { bubbles: true }));
  assert.equal(t.doc.querySelector('[data-staff-numbers]').hidden, true);
  assert.equal(t.doc.activeElement, search);
});
