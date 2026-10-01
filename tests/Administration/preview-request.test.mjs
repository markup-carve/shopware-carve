import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PreviewRequest, escapeInline, administrationLanguageId } from '../../src/Resources/app/administration/src/component/carve-editor/preview-request.js';

const tick = () => new Promise((resolve) => setTimeout(resolve, 5));

test('a slower earlier preview cannot overwrite the latest source', async () => {
    const pending = [];
    const states = [];
    const request = new PreviewRequest((payload) => new Promise((resolve, reject) => pending.push({ payload, resolve, reject })),
        (state) => states.push(state), 0);
    request.update({ source: 'old' });
    await tick();
    request.update({ source: 'new' });
    await tick();
    pending[1].resolve({ html: 'new' });
    await tick();
    pending[0].resolve({ html: 'old' });
    await tick();
    assert.equal(states.at(-1).result.html, 'new');
    request.dispose();
});

test('rapid edits are debounced and disposed requests cannot update a component', async () => {
    let calls = 0;
    let resolve;
    const states = [];
    const request = new PreviewRequest(() => { calls++; return new Promise((done) => { resolve = done; }); },
        (state) => states.push(state), 0);
    request.update({ source: 'a' });
    request.update({ source: 'b' });
    await tick();
    assert.equal(calls, 1);
    const count = states.length;
    request.dispose();
    resolve({ html: 'late' });
    await tick();
    assert.equal(states.length, count);
});

test('failures clear stale output and are visible', async () => {
    const states = [];
    const request = new PreviewRequest(() => Promise.reject(new Error('Offline')), (state) => states.push(state), 0);
    request.update({ source: 'new' });
    await tick();
    assert.equal(states.at(-1).error.message, 'Offline');
    assert.equal(states.at(-1).result, null);
    request.dispose();
});

test('product numbers escape inline delimiters', () => {
    assert.equal(escapeInline('A]B\\C'), 'A\\]B\\\\C');
});

test('language lookup supports Pinia and a throwing legacy context lookup', () => {
    assert.equal(administrationLanguageId({ Store: { get: () => ({ api: { languageId: 'pinia' } }) } }), 'pinia');
    assert.equal(administrationLanguageId({
        Store: { get: () => { throw new Error('Store with id "context" not found'); } },
        State: { get: () => ({ api: { languageId: 'vuex' } }) },
    }), 'vuex');
});

test('disposed preview requests cannot restart when settings arrive late', async () => {
    const request = new PreviewRequest(() => assert.fail('disposed request fetched'), () => assert.fail('disposed request updated'), 0);
    request.dispose();
    request.update({ source: 'late settings' });
    await tick();
});
