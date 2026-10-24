import assert from 'node:assert/strict';
import test from 'node:test';

import { loadVideo } from '../../src/Resources/app/storefront/src/carve-widgets.js';

test('video iframe is created only when a validated button is activated', () => {
    let frame;
    const button = {
        dataset: { videoId: 'ABCDEFGHIJK' },
        ownerDocument: { createElement(tag) { assert.equal(tag, 'iframe'); frame = {}; return frame; } },
        replaceWith(value) { assert.equal(value, frame); this.replaced = true; },
    };
    assert.equal(frame, undefined);
    assert.equal(loadVideo(button), true);
    assert.equal(frame.src, 'https://www.youtube-nocookie.com/embed/ABCDEFGHIJK?autoplay=1');
    assert.equal(frame.referrerPolicy, 'no-referrer');
    assert.equal(button.replaced, true);

    const invalid = { ...button, dataset: { videoId: 'bad?x=1' }, replaced: false };
    assert.equal(loadVideo(invalid), false);
    assert.equal(invalid.replaced, false);
});
