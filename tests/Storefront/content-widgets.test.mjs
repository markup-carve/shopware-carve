import assert from 'node:assert/strict';
import test from 'node:test';

import { copyCoupon, countdownText } from '../../src/Resources/app/storefront/src/carve-widgets.js';

test('coupon copy reports success only after the clipboard accepts the code', async () => {
    let written;
    const button = { dataset: { code: 'SAVE-20' }, textContent: 'Copy', setAttribute(name, value) { this[name] = value; } };
    const clipboard = { async writeText(value) { written = value; } };

    assert.equal(await copyCoupon(button, clipboard), true);
    assert.equal(written, 'SAVE-20');
    assert.equal(button.textContent, 'Copied');
    assert.equal(button['aria-label'], 'Copied coupon SAVE-20');

    const failed = { dataset: { code: 'NOPE' }, textContent: 'Copy', setAttribute() {} };
    assert.equal(await copyCoupon(failed, { writeText: async () => { throw new Error('denied'); } }), false);
    assert.equal(failed.textContent, 'Copy');
});

test('countdown uses UTC midnight and stays readable when it ends', () => {
    assert.equal(countdownText('2027-01-02', Date.parse('2027-01-01T12:00:00Z')), '0d 12h 00m');
    assert.equal(countdownText('2027-01-02', Date.parse('2027-01-02T00:00:00Z')), 'Ended');
    assert.equal(countdownText('invalid', 0), 'invalid');
});
