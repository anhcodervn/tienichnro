export function initializeNroServerSelect(root) {
    const container = root.querySelector('[data-nro-server-select]');
    if (!container) return;
    const select = container.querySelector('[data-nro-server-native]');
    const custom = container.querySelector('[data-nro-server-custom]');
    const trigger = container.querySelector('[data-nro-server-trigger]');
    const list = container.querySelector('[data-nro-server-list]');
    const label = container.querySelector('[data-nro-server-value]');
    const dot = container.querySelector('[data-nro-server-dot]');
    const status = container.querySelector('[data-nro-server-status]');
    const options = Array.from(container.querySelectorAll('[data-nro-server-option]'));
    if (!select || !custom || !trigger || !list || !label || !dot || !status || !options.length) return;

    const synchronize = () => {
        const selected = options.find((option) => option.dataset.value === select.value) || options[0];
        label.textContent = selected.dataset.name;
        status.textContent = selected.dataset.status === 'online' ? 'Hoạt động' : selected.dataset.status === 'offline' ? 'Không hoạt động' : '';
        dot.classList.remove('bg-slate-400', 'bg-emerald-500', 'bg-rose-500');
        dot.classList.add(
            selected.dataset.status === 'online' ? 'bg-emerald-500' : selected.dataset.status === 'offline' ? 'bg-rose-500' : 'bg-slate-400',
        );
        options.forEach((option) => option.setAttribute('aria-selected', String(option === selected)));
    };
    const close = (restoreFocus = false) => {
        list.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) trigger.focus();
    };
    const open = (index = options.findIndex((option) => option.dataset.value === select.value)) => {
        list.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        options[Math.max(0, index)]?.focus();
    };
    trigger.addEventListener('click', () => (list.hidden ? open() : close()));
    trigger.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        event.preventDefault();
        open(event.key === 'ArrowUp' ? options.length - 1 : undefined);
    });
    options.forEach((option, index) => {
        option.addEventListener('click', () => {
            select.value = option.dataset.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            close(true);
        });
        option.addEventListener('keydown', (event) => {
            const target = {
                ArrowDown: (index + 1) % options.length,
                ArrowUp: (index - 1 + options.length) % options.length,
                Home: 0,
                End: options.length - 1,
            }[event.key];
            if (target === undefined) return;
            event.preventDefault();
            options[target].focus();
        });
    });
    container.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault();
            close(true);
        }
    });
    container.addEventListener('focusout', (event) => {
        if (!container.contains(event.relatedTarget)) close();
    });
    document.addEventListener('pointerdown', (event) => {
        if (!container.contains(event.target)) close();
    });
    select.addEventListener('change', synchronize);
    synchronize();
    select.hidden = true;
    custom.hidden = false;
    return synchronize;
}
