export function createAutoSave({ save, canSave, isDirty, clock = globalThis }) {
    let timer;
    let interval;
    let running = false;
    let active = false;
    const schedule = () => {
        if (!active) return;
        clock.clearTimeout(timer);
        timer = clock.setTimeout(run, 700);
    };
    const run = async () => {
        if (!active || running || !isDirty() || !canSave()) return;
        clock.clearTimeout(timer);
        running = true;
        let saved = false;
        try {
            saved = await save();
        } catch {
            saved = false;
        } finally {
            running = false;
            if (saved && isDirty()) schedule();
        }
    };
    return {
        schedule,
        start() {
            if (active) return;
            active = true;
            interval = clock.setInterval(run, 5000);
            schedule();
        },
        stop() {
            active = false;
            clock.clearTimeout(timer);
            clock.clearInterval(interval);
        },
    };
}
