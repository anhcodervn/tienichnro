import assert from 'node:assert/strict';
import test from 'node:test';
import { initializeGameImageLogModal, initializeGameImageRealtime } from '../../resources/js/client-game-image-realtime.js';

function fixture() {
    const sources = [];
    const listeners = {};
    const status = {};
    let restored = 0;
    let bindings = 0;
    const cell = {
        dataset: { username: 'revealed-account' },
        querySelector: (selector) => (selector === 'button' ? { getAttribute: () => 'true' } : { click: () => restored++ }),
    };
    const initialLogs = [];
    const updatedLogs = [];
    const content = {
        innerHTML: 'original',
        querySelectorAll(selector) {
            return selector.startsWith('textarea') ? (this.innerHTML === 'original' ? initialLogs : updatedLogs) : [cell];
        },
    };
    const section = {
        dataset: { streamUrl: '/ghep-anh-game/trang-thai/stream?status=error&page=2' },
        querySelector: (selector) => (selector.includes('content') ? content : status),
    };
    const root = {
        hidden: false,
        querySelectorAll: () => [section],
        addEventListener: (name, handler) => (listeners[name] = handler),
    };
    const environment = {
        addEventListener: (name, handler) => (listeners[name] = handler),
        EventSource: class {
            constructor(url) {
                this.url = url;
                this.listeners = {};
                sources.push(this);
            }
            addEventListener(name, handler) {
                this.listeners[name] = handler;
            }
            close() {
                this.closed = true;
            }
            emit(name, data) {
                this.listeners[name]({ data });
            }
        },
    };
    const initialize = () => initializeGameImageRealtime(root, environment, () => bindings++);
    return {
        initialize,
        sources,
        listeners,
        root,
        content,
        status,
        environment,
        initialLogs,
        updatedLogs,
        bindings: () => bindings,
        restored: () => restored,
    };
}

test('updates only table rebinds actions preserves revealed username and skips invalid or duplicate snapshots', () => {
    const f = fixture();
    f.initialize();
    f.initialize();
    assert.equal(f.sources.length, 1);
    const source = f.sources[0];
    assert.match(source.url, /status=error&page=2/);
    source.emit('accounts', 'invalid');
    source.emit('accounts', '{}');
    assert.equal(f.content.innerHTML, 'original');
    const snapshot = JSON.stringify({ html: '<table>updated</table>', signature: 'changed' });
    source.emit('accounts', snapshot);
    source.emit('accounts', snapshot);
    assert.equal(f.content.innerHTML, '<table>updated</table>');
    assert.equal(f.bindings(), 1);
    assert.equal(f.restored(), 1);
});

test('visibility and navigation close streams reconnect once and ignore stale events', () => {
    const f = fixture();
    f.initialize();
    f.root.hidden = true;
    f.listeners.visibilitychange();
    assert.equal(f.sources[0].closed, true);
    f.root.hidden = false;
    f.listeners.visibilitychange();
    f.listeners.pageshow();
    assert.equal(f.sources.length, 2);
    f.sources[0].emit('accounts', JSON.stringify({ html: 'stale', signature: 'old' }));
    assert.equal(f.content.innerHTML, 'original');
    f.listeners.pagehide();
    assert.equal(f.sources[1].closed, true);
});

test('keeps the log scroll position or follows new entries when already at the bottom', () => {
    const f = fixture();
    const log = (key, scrollTop, scrollHeight = 200) => ({
        dataset: { gameImageCaptureLog: key },
        scrollTop,
        scrollHeight,
        clientHeight: 100,
        focus(options) {
            this.focusOptions = options;
        },
    });
    f.initialLogs.push(log('1:1', 40), log('2:1', 100), log('3:1', 80));
    f.root.activeElement = f.initialLogs[0];
    f.updatedLogs.push(log('1:1', 0, 260), log('2:1', 0, 260), log('3:2', 0));
    f.initialize();
    f.sources[0].emit('accounts', JSON.stringify({ html: 'updated', signature: 'changed' }));
    assert.deepEqual(
        f.updatedLogs.map(({ scrollTop }) => scrollTop),
        [40, 160, 0],
    );
    assert.deepEqual(f.updatedLogs[0].focusOptions, { preventScroll: true });
});

test('terminal access events stop reconnect and unsupported browsers retain the table', () => {
    const f = fixture();
    f.initialize();
    f.sources[0].emit('access-expired');
    assert.equal(f.sources[0].closed, true);
    f.listeners.pageshow();
    assert.equal(f.sources.length, 1);
    const unsupported = fixture();
    delete unsupported.environment.EventSource;
    unsupported.initialize();
    assert.equal(unsupported.content.innerHTML, 'original');
    assert.match(unsupported.status.textContent, /Tải lại trang/);
});

test('terminal modal opens the selected account safely and follows realtime logs without stealing scroll', () => {
    const events = {};
    let click;
    let closeClick;
    let focused = 0;
    const title = {};
    const terminal = { textContent: '', scrollTop: 0, scrollHeight: 400, clientHeight: 100 };
    const button = { dataset: { gameImageViewLog: '12' }, focus: () => focused++ };
    let logs = [
        { dataset: { gameImageCaptureLog: '12' }, value: '[12:00] <script>alert(1)</script>' },
        { dataset: { gameImageCaptureLog: '99' }, value: 'unrelated account' },
    ];
    const content = { querySelectorAll: (selector) => (selector === '[data-game-image-capture-log]' ? logs : [button]) };
    const dialog = {
        open: false,
        showModal() {
            this.open = true;
        },
        close() {
            this.open = false;
            events.close();
        },
        querySelector: (selector) =>
            ({
                '[data-game-image-log-terminal]': terminal,
                '[data-game-image-log-title]': title,
                '[data-game-image-log-close]': { addEventListener: (_name, fn) => (closeClick = fn) },
            })[selector],
        addEventListener: (name, fn) => (events[name] = fn),
        getBoundingClientRect: () => ({ left: 10, right: 100, top: 10, bottom: 100 }),
    };
    const root = { querySelector: () => dialog, documentElement: { style: { overflow: 'auto' } } };
    const modal = initializeGameImageLogModal(root, { addEventListener: (_name, fn) => (click = fn) }, content);
    click({ target: { closest: () => button } });
    assert.equal(dialog.open, true);
    assert.equal(root.documentElement.style.overflow, 'hidden');
    assert.match(title.textContent, /#12/);
    assert.equal(terminal.textContent, logs[0].value);
    assert.equal(terminal.scrollTop, 400);
    terminal.scrollTop = 40;
    logs = [{ dataset: { gameImageCaptureLog: '12' }, value: 'new realtime line' }];
    modal.sync();
    assert.equal(terminal.textContent, 'new realtime line');
    assert.equal(terminal.scrollTop, 40);
    terminal.scrollTop = 300;
    logs[0].value += '\ndone';
    modal.sync();
    assert.equal(terminal.scrollTop, 400);
    events.click({ target: dialog, clientX: 30, clientY: 30 });
    assert.equal(dialog.open, true);
    events.click({ target: dialog, clientX: 1, clientY: 1 });
    assert.equal(dialog.open, false);
    assert.equal(root.documentElement.style.overflow, 'auto');
    assert.equal(terminal.textContent, '');
    assert.equal(focused, 1);
    click({ target: { closest: () => button } });
    closeClick();
    assert.equal(dialog.open, false);
    click({ target: { closest: () => button } });
    modal.close();
    assert.equal(dialog.open, false);
});
