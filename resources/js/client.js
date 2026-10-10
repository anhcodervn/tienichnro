import { initializeClientCatalogModals } from './client-catalog-modals';
import { initializeClientAuth } from './client-auth';
import { initializeClientNotifications } from './client-notifications';
import { initializePotentialCalculator } from './client-potential';
import { initializeClientTheme } from './client-theme';
import { initializeNotificationSubscriptions } from './client-subscriptions';
import { initializeNroAccess } from './nro-access';
import { initializeNroNotifications } from './nro-notifications';

initializeClientTheme();
void initializeClientNotifications();
initializeClientAuth();
initializePotentialCalculator();
initializeNroAccess();

initializeClientCatalogModals();
initializeNotificationSubscriptions();

const imageViewerContainers = Array.from(document.querySelectorAll('[data-client-image-viewer]')).filter(
    (container) => container instanceof HTMLElement && container.querySelector('img[src]'),
);

if (imageViewerContainers.length > 0) {
    const viewer = document.createElement('div');
    viewer.className = 'client-image-lightbox';
    viewer.hidden = true;
    viewer.setAttribute('role', 'dialog');
    viewer.setAttribute('aria-modal', 'true');
    viewer.setAttribute('aria-label', 'Xem ảnh lớn');
    viewer.innerHTML = `
        <button type="button" class="client-image-lightbox__backdrop" data-image-viewer-close aria-label="Đóng xem ảnh"></button>
        <div class="client-image-lightbox__panel">
            <img class="client-image-lightbox__image" data-image-viewer-image alt="">
            <div class="client-image-lightbox__toolbar" aria-label="Điều khiển ảnh">
                <button type="button" data-image-viewer-previous aria-label="Ảnh trước">‹</button>
                <button type="button" data-image-viewer-zoom-out aria-label="Thu nhỏ">−</button>
                <button type="button" data-image-viewer-reset aria-label="Đặt lại kích thước">100%</button>
                <button type="button" data-image-viewer-zoom-in aria-label="Phóng to">+</button>
                <button type="button" data-image-viewer-next aria-label="Ảnh tiếp theo">›</button>
                <button type="button" data-image-viewer-close aria-label="Đóng xem ảnh">×</button>
            </div>
        </div>`;
    document.body.appendChild(viewer);

    const viewerImage = viewer.querySelector('[data-image-viewer-image]');
    const resetButton = viewer.querySelector('[data-image-viewer-reset]');
    const previousButton = viewer.querySelector('[data-image-viewer-previous]');
    const nextButton = viewer.querySelector('[data-image-viewer-next]');
    let activeImages = [];
    let activeIndex = 0;
    let scale = 1;
    let lastFocusedElement = null;

    const renderViewerImage = () => {
        const image = activeImages[activeIndex];
        if (!(viewerImage instanceof HTMLImageElement) || !(image instanceof HTMLImageElement)) return;

        viewerImage.src = image.currentSrc || image.src;
        viewerImage.alt = image.alt || '';
        viewerImage.style.transform = `scale(${scale})`;
        if (resetButton) resetButton.textContent = `${Math.round(scale * 100)}%`;
        if (previousButton instanceof HTMLButtonElement) previousButton.hidden = activeImages.length < 2;
        if (nextButton instanceof HTMLButtonElement) nextButton.hidden = activeImages.length < 2;
    };

    const setScale = (nextScale) => {
        scale = Math.min(4, Math.max(0.25, nextScale));
        renderViewerImage();
    };

    const closeViewer = () => {
        viewer.hidden = true;
        document.body.classList.remove('client-image-lightbox-open');
        if (lastFocusedElement instanceof HTMLElement) lastFocusedElement.focus({ preventScroll: true });
    };

    const openViewer = (image, images) => {
        activeImages = images;
        activeIndex = Math.max(0, images.indexOf(image));
        scale = 1;
        lastFocusedElement = image;
        viewer.hidden = false;
        document.body.classList.add('client-image-lightbox-open');
        renderViewerImage();
        viewer.querySelector('[data-image-viewer-close]')?.focus({ preventScroll: true });
    };

    const showAdjacentImage = (offset) => {
        if (activeImages.length < 2) return;
        activeIndex = (activeIndex + offset + activeImages.length) % activeImages.length;
        scale = 1;
        renderViewerImage();
    };

    imageViewerContainers.forEach((container) => {
        const images = Array.from(container.querySelectorAll('img[src]'));

        images.forEach((image) => {
            image.tabIndex = 0;
            image.setAttribute('role', 'button');
            image.setAttribute('aria-label', image.alt ? `Xem ảnh lớn: ${image.alt}` : 'Xem ảnh lớn');

            image.addEventListener('click', (event) => {
                event.preventDefault();
                openViewer(image, images);
            });

            image.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openViewer(image, images);
                }
            });
        });
    });

    viewer.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        if (target.closest('[data-image-viewer-close]')) closeViewer();
        if (target.closest('[data-image-viewer-zoom-in]')) setScale(scale + 0.2);
        if (target.closest('[data-image-viewer-zoom-out]')) setScale(scale - 0.2);
        if (target.closest('[data-image-viewer-reset]')) setScale(1);
        if (target.closest('[data-image-viewer-previous]')) showAdjacentImage(-1);
        if (target.closest('[data-image-viewer-next]')) showAdjacentImage(1);
    });

    viewer.addEventListener(
        'wheel',
        (event) => {
            event.preventDefault();
            setScale(scale + (event.deltaY < 0 ? 0.2 : -0.2));
        },
        { passive: false },
    );

    document.addEventListener('keydown', (event) => {
        if (viewer.hidden) return;

        if (event.key === 'Escape') closeViewer();
        if (event.key === 'ArrowLeft') showAdjacentImage(-1);
        if (event.key === 'ArrowRight') showAdjacentImage(1);
        if (event.key === '+' || event.key === '=') setScale(scale + 0.2);
        if (event.key === '-') setScale(scale - 0.2);
    });
}

initializeNroNotifications();

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    document.querySelectorAll('details[data-client-menu][open]').forEach((menu) => {
        if (!(menu instanceof HTMLDetailsElement)) return;
        if (!menu.contains(event.target) || event.target.closest('a')) menu.open = false;
    });
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('details[data-client-menu][open]').forEach((menu) => {
        if (!(menu instanceof HTMLDetailsElement)) return;
        menu.open = false;
        menu.querySelector('summary')?.focus();
    });
});
