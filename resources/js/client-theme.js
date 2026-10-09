export function initializeClientTheme(root = document, browser = window) {
    const button = root.querySelector('[data-client-theme-toggle]');
    if (!button || button.dataset.themeReady) return;
    button.dataset.themeReady = 'true';
    const media = browser.matchMedia('(prefers-color-scheme: dark)');
    let preference;
    try {
        preference = browser.localStorage.getItem('client-theme');
    } catch {
        // Private browsing can disable storage; the toggle still works for this page.
    }
    const render = (dark) => {
        root.documentElement.classList.toggle('dark', dark);
        button.setAttribute('aria-pressed', String(dark));
        const label = dark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối';
        button.setAttribute('aria-label', label);
        button.title = label;
    };
    const followPreference = () => render(preference === 'dark' || (preference !== 'light' && media.matches));
    followPreference();
    button.addEventListener('click', () => {
        preference = root.documentElement.classList.contains('dark') ? 'light' : 'dark';
        try {
            browser.localStorage.setItem('client-theme', preference);
        } catch {
            // Keep the selected theme in memory when storage is unavailable.
        }
        followPreference();
    });
    media.addEventListener('change', followPreference);
    browser.addEventListener('storage', (event) => {
        if (event.key !== 'client-theme' && event.key !== null) return;
        preference = event.newValue;
        followPreference();
    });
}
