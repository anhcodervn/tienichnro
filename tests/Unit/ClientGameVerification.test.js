import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { exportVerificationAccounts, reconcileVerificationSelection, verificationSmsUrl } from '../../resources/js/client-game-verification.js';

test('verification actions offer SMS copy and sending without password copy buttons', () => {
    const page = readFileSync(new URL('../../resources/views/pages/tools/game-verification.blade.php', import.meta.url), 'utf8');
    const history = readFileSync(new URL('../../resources/views/pages/tools/partials/game-verification-history.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(page, /data-verification-export="password"/);
    assert.doesNotMatch(history, /data-verification-password-one/);
    assert.match(page, /data-verification-export="sms"/);
    assert.match(page, /data-verification-sms\b/);
    assert.match(history, /data-verification-copy-one/);
    assert.match(history, /data-verification-sms-one/);
});

test('SMS links open 6020 with the exact encoded syntax on Android and iOS', () => {
    const syntax = 'GO NRO-XT my-account A1b2C3d4';
    assert.equal(verificationSmsUrl(syntax, 'Android'), `sms:6020?body=${encodeURIComponent(syntax)}`);
    assert.equal(verificationSmsUrl(syntax, 'iPhone'), `sms:6020&body=${encodeURIComponent(syntax)}`);
    assert.throws(() => verificationSmsUrl('javascript:alert(1)'));
    assert.throws(() => verificationSmsUrl(syntax + '\nextra'));
    assert.throws(() => verificationSmsUrl('GO NRO-XT account invalid'));
});

test('realtime table updates retain selected eligible rows and remove missing or unfinished ones', () => {
    const selected = new Set(['1', '2', '3']);
    const rows = [
        { dataset: { verificationSelect: '1' }, disabled: false },
        { dataset: { verificationSelect: '2' }, disabled: true },
        { dataset: { verificationSelect: '4' }, disabled: false },
    ];
    reconcileVerificationSelection(selected, rows);
    assert.deepEqual([...selected], ['1']);
    assert.equal(rows[0].checked, true);
    assert.equal(rows[1].checked, false);
    assert.equal(rows[2].checked, false);
});

test('exports send selected IDs and CSRF without account credentials and preserve response ordering', async () => {
    const request = async (url, options) => {
        assert.equal(url, '/verification/export');
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf');
        assert.deepEqual(JSON.parse(options.body), { ids: [2, 1], format: 'sms' });
        return {
            ok: true,
            json: async () => ({
                data: [
                    { id: 2, syntax: 'second' },
                    { id: 1, syntax: 'first' },
                ],
            }),
        };
    };
    const result = await exportVerificationAccounts('/verification/export', ['2', '1'], 'sms', undefined, 'csrf', request);
    assert.deepEqual(
        result.map((row) => row.id),
        [2, 1],
    );
});

test('password export requires an explicitly entered new password and displays safe server errors', async () => {
    await exportVerificationAccounts('/export', ['1'], 'password', 'new-secret', 'csrf', async (_url, options) => {
        assert.deepEqual(JSON.parse(options.body), { ids: [1], format: 'password', new_password: 'new-secret' });
        return { ok: true, json: async () => ({ data: [{ line: 'account|old|3|new-secret' }] }) };
    });
    await assert.rejects(
        exportVerificationAccounts('/export', ['1'], 'sms', undefined, 'csrf', async () => ({
            ok: false,
            json: async () => ({ errors: { ids: ['Forbidden selection'] } }),
        })),
        /Forbidden selection/,
    );
});
