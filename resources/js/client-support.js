import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const chat = document.querySelector('[data-support-chat]');

if (chat) {
    const messageArea = chat.querySelector('[data-support-message-area]');
    const messageList = chat.querySelector('[data-support-messages]');
    const loadingState = chat.querySelector('[data-support-loading]');
    const emptyState = chat.querySelector('[data-support-empty]');
    const loadOlderButton = chat.querySelector('[data-support-load-older]');
    const loadOlderText = chat.querySelector('[data-support-load-older-text]');
    const refreshButton = chat.querySelector('[data-support-refresh]');
    const newMessageButton = chat.querySelector('[data-support-new-message]');
    const form = chat.querySelector('[data-support-form]');
    const input = chat.querySelector('[data-support-input]');
    const sendButton = chat.querySelector('[data-support-send]');
    const sendIcon = chat.querySelector('[data-support-send-icon]');
    const sendLabel = chat.querySelector('[data-support-send-label]');
    const characterCount = chat.querySelector('[data-support-character-count]');
    const errorBox = chat.querySelector('[data-support-error]');
    const connectionText = chat.querySelector('[data-support-connection-text]');
    const connectionDot = chat.querySelector('[data-support-connection-dot]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const messages = new Map();
    let conversationId = null;
    let nextCursor = null;
    let hasMore = false;
    let loading = false;
    let sending = false;
    let cooldownUntil = 0;
    let cooldownTimer = null;
    let echo = null;

    const requestJson = async (url, options = {}) => {
        const method = options.method || 'GET';
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                ...(method !== 'GET' ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken } : {}),
                ...options.headers,
            },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload.status === false) {
            const error = new Error(payload.message || 'Không thể kết nối tới hệ thống hỗ trợ.');
            error.status = response.status;
            error.retryAfter = Number(response.headers.get('Retry-After') || payload.data?.retry_after || 0);

            throw error;
        }

        return payload.data;
    };

    const setError = (message = '') => {
        if (!errorBox) return;

        errorBox.textContent = message;
        errorBox.classList.toggle('hidden', message === '');
    };

    const setConnection = (state, label) => {
        if (connectionText) connectionText.textContent = label;
        if (!connectionDot) return;

        connectionDot.classList.remove('bg-amber-400', 'bg-emerald-500', 'bg-rose-500', 'motion-safe:animate-pulse');
        connectionDot.classList.add(state === 'connected' ? 'bg-emerald-500' : state === 'connecting' ? 'bg-amber-400' : 'bg-rose-500');
        if (state === 'connecting') connectionDot.classList.add('motion-safe:animate-pulse');
    };

    const cooldownSeconds = () => Math.max(0, Math.ceil((cooldownUntil - Date.now()) / 1000));

    const updateSendButton = () => {
        const remaining = cooldownSeconds();
        const isCoolingDown = remaining > 0;

        sendButton?.toggleAttribute('disabled', sending || isCoolingDown);
        if (sendLabel) sendLabel.textContent = sending ? 'Đang gửi' : isCoolingDown ? `Gửi sau ${remaining}s` : 'Gửi';
        sendButton?.setAttribute('aria-label', isCoolingDown ? `Có thể gửi tin tiếp theo sau ${remaining} giây` : 'Gửi tin nhắn');

        return remaining;
    };

    const startCooldown = (seconds) => {
        const safeSeconds = Math.max(1, Math.ceil(Number(seconds) || 10));
        cooldownUntil = Math.max(cooldownUntil, Date.now() + safeSeconds * 1000);
        updateSendButton();

        if (cooldownTimer !== null) window.clearInterval(cooldownTimer);
        cooldownTimer = window.setInterval(() => {
            if (updateSendButton() > 0) return;

            window.clearInterval(cooldownTimer);
            cooldownTimer = null;
            cooldownUntil = 0;
        }, 250);
    };

    const formatTime = (value) => {
        if (!value) return 'Vừa xong';

        const date = new Date(value);
        const today = new Date();

        return date.toDateString() === today.toDateString()
            ? date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
            : date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    const messageElement = (message) => {
        const fromUser = message.sender_role === 'user';
        const wrapper = document.createElement('article');
        const content = document.createElement('div');
        const bubble = document.createElement('p');
        const meta = document.createElement('div');
        const sender = document.createElement('span');
        const time = document.createElement('time');

        wrapper.dataset.supportMessageId = String(message.id);
        wrapper.className = `flex ${fromUser ? 'justify-end' : 'justify-start'}`;
        content.className = 'max-w-[88%] sm:max-w-[75%]';
        bubble.className = fromUser
            ? 'whitespace-pre-wrap break-words rounded-[14px] rounded-br-[4px] bg-emerald-600 px-3.5 py-2.5 text-sm leading-6 text-white shadow-sm'
            : 'whitespace-pre-wrap break-words rounded-[14px] rounded-bl-[4px] border border-slate-200 bg-white px-3.5 py-2.5 text-sm leading-6 text-slate-800 shadow-sm';
        meta.className = `mt-1 flex items-center gap-2 px-1 text-[10px] text-slate-400 ${fromUser ? 'justify-end' : 'justify-start'}`;
        sender.textContent = fromUser ? (message.read_at ? 'Đã xem' : 'Đã gửi') : 'Nhân viên hỗ trợ';
        time.dateTime = message.created_at || '';
        time.textContent = formatTime(message.created_at);
        bubble.textContent = message.message;
        meta.append(sender, time);
        content.append(bubble, meta);
        wrapper.append(content);

        return wrapper;
    };

    const renderMessages = () => {
        if (!messageList) return;

        const fragment = document.createDocumentFragment();
        [...messages.values()]
            .sort((left, right) => Number(left.id) - Number(right.id))
            .forEach((message) => fragment.append(messageElement(message)));
        messageList.replaceChildren(fragment);
        emptyState?.toggleAttribute('hidden', messages.size > 0);
    };

    const mergeMessages = (incoming) => {
        incoming.forEach((message) => {
            const current = messages.get(Number(message.id));
            messages.set(Number(message.id), current ? { ...current, ...message } : message);
        });
        renderMessages();
    };

    const isNearBottom = () => !messageArea || messageArea.scrollHeight - messageArea.scrollTop - messageArea.clientHeight < 120;

    const scrollToBottom = (behavior = 'smooth') => {
        if (!messageArea) return;

        requestAnimationFrame(() => messageArea.scrollTo({ top: messageArea.scrollHeight, behavior }));
        newMessageButton?.classList.add('hidden');
        newMessageButton?.classList.remove('inline-flex');
    };

    const updatePagination = (meta = {}) => {
        nextCursor = meta.next_cursor || null;
        hasMore = Boolean(meta.has_more && nextCursor);
        loadOlderButton?.classList.toggle('hidden', !hasMore);
        loadOlderButton?.classList.toggle('inline-flex', hasMore);
    };

    const markRead = async () => {
        if (!conversationId || document.visibilityState === 'hidden') return;

        try {
            const result = await requestJson(chat.dataset.readUrl, { method: 'POST', body: '{}' });
            const readAt = new Date().toISOString();

            (result.message_ids || []).forEach((id) => {
                const message = messages.get(Number(id));
                if (message) messages.set(Number(id), { ...message, read_at: readAt });
            });
            renderMessages();
        } catch {
            // Read receipts are retried on the next load, focus, or incoming message.
        }
    };

    const loadThread = async ({ cursor = null, older = false } = {}) => {
        if (loading || (older && (!hasMore || !cursor))) return;

        const previousHeight = messageArea?.scrollHeight || 0;
        loading = true;
        setError();
        refreshButton?.setAttribute('disabled', 'disabled');
        if (older) {
            loadOlderButton?.setAttribute('disabled', 'disabled');
            if (loadOlderText) loadOlderText.textContent = 'Đang tải...';
        }

        try {
            const url = new URL(chat.dataset.threadUrl, window.location.origin);
            if (cursor) url.searchParams.set('cursor', cursor);
            const result = await requestJson(url.toString());

            if (result.conversation) conversationId = Number(result.conversation.id);
            mergeMessages(result.messages || []);
            updatePagination(result.meta);
            loadingState?.setAttribute('hidden', 'hidden');
            messageArea?.setAttribute('aria-busy', 'false');

            if (older && messageArea) {
                requestAnimationFrame(() => {
                    messageArea.scrollTop += messageArea.scrollHeight - previousHeight;
                });
            } else {
                scrollToBottom('auto');
                void markRead();
            }
        } catch (error) {
            loadingState?.setAttribute('hidden', 'hidden');
            messageArea?.setAttribute('aria-busy', 'false');
            setError(error.message);
        } finally {
            loading = false;
            refreshButton?.removeAttribute('disabled');
            loadOlderButton?.removeAttribute('disabled');
            if (loadOlderText) loadOlderText.textContent = 'Tải tin nhắn cũ';
        }
    };

    const sendMessage = async () => {
        const content = input?.value.trim() || '';
        if (!content || sending || cooldownSeconds() > 0) return;

        sending = true;
        setError();
        updateSendButton();
        sendIcon?.classList.remove('bx-send');
        sendIcon?.classList.add('bx-loader-lines', 'animate-spin');

        try {
            const result = await requestJson(chat.dataset.sendUrl, {
                method: 'POST',
                body: JSON.stringify({ message: content }),
            });
            conversationId = Number(result.conversation.id);
            mergeMessages([result.message]);
            input.value = '';
            input.style.height = 'auto';
            if (characterCount) characterCount.textContent = '0';
            emptyState?.setAttribute('hidden', 'hidden');
            scrollToBottom();
            startCooldown(10);
        } catch (error) {
            setError(error.message);
            if (error.status === 429) startCooldown(error.retryAfter || 10);
        } finally {
            sending = false;
            sendIcon?.classList.remove('bx-loader-lines', 'animate-spin');
            sendIcon?.classList.add('bx-send');
            updateSendButton();
            input?.focus();
        }
    };

    const setupRealtime = () => {
        const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
        const channelName = chat.dataset.channel;

        if (!reverbKey || !channelName) {
            setConnection('unavailable', 'Realtime chưa cấu hình');
            return;
        }

        window.Pusher = Pusher;
        setConnection('connecting', 'Đang kết nối...');
        echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        const channel = echo.private(channelName);
        channel.listen('.support.message.created', (event) => {
            const incomingConversationId = Number(event.conversation?.id || event.message?.conversation_id);
            if (conversationId && incomingConversationId !== conversationId) return;

            const shouldFollow = isNearBottom();
            conversationId = incomingConversationId;
            mergeMessages([event.message]);

            if (event.message.sender_role === 'admin') void markRead();
            if (shouldFollow) {
                scrollToBottom();
            } else {
                newMessageButton?.classList.remove('hidden');
                newMessageButton?.classList.add('inline-flex');
            }
        });
        channel.listen('.support.messages.read', (event) => {
            if (conversationId && Number(event.conversation_id) !== conversationId) return;

            (event.message_ids || []).forEach((id) => {
                const message = messages.get(Number(id));
                if (message) messages.set(Number(id), { ...message, read_at: event.read_at });
            });
            renderMessages();
        });
        channel.listen('.support.conversation.updated', (event) => {
            if (!conversationId && event.conversation?.id) conversationId = Number(event.conversation.id);
        });

        const connection = echo.connector.pusher.connection;
        connection.bind('connected', () => setConnection('connected', 'Đang online realtime'));
        connection.bind('disconnected', () => setConnection('connecting', 'Đang kết nối lại...'));
        connection.bind('error', () => setConnection('error', 'Kết nối realtime bị gián đoạn'));
    };

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        void sendMessage();
    });
    input?.addEventListener('input', () => {
        if (characterCount) characterCount.textContent = String(input.value.length);
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 144)}px`;
    });
    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void sendMessage();
        }
    });
    messageArea?.addEventListener('scroll', () => {
        if (isNearBottom()) {
            newMessageButton?.classList.add('hidden');
            newMessageButton?.classList.remove('inline-flex');
        }
    });
    loadOlderButton?.addEventListener('click', () => void loadThread({ cursor: nextCursor, older: true }));
    refreshButton?.addEventListener('click', () => void loadThread());
    newMessageButton?.addEventListener('click', () => scrollToBottom());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') void markRead();
    });
    window.addEventListener('pagehide', () => {
        if (cooldownTimer !== null) window.clearInterval(cooldownTimer);
        if (echo && chat.dataset.channel) {
            echo.leave(chat.dataset.channel);
            echo.disconnect();
        }
    });

    setupRealtime();
    void loadThread();
}
