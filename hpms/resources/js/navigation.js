const MOBILE_QUERY = '(max-width: 1023px), (orientation: landscape) and (max-height: 500px)';
const OPEN_DISTANCE = 64;
const CLOSE_DISTANCE = 64;
const MAX_DURATION = 700;

let gesture = null;
let suppressClick = false;

const isMobile = () => window.matchMedia(MOBILE_QUERY).matches;
const sidebar = () => document.getElementById('sidebar');

const controlSidebar = (open) => {
    window.dispatchEvent(new CustomEvent('sidebar-control', { detail: { open } }));
};

const resetGesture = () => {
    gesture = null;
};

document.addEventListener('touchstart', (event) => {
    if (!isMobile() || event.touches.length !== 1) {
        resetGesture();

        return;
    }

    const panel = sidebar();
    if (!panel) {
        return;
    }

    const touch = event.touches[0];
    const target = event.target;
    const isOpen = panel.classList.contains('sidebar-open');

    if (target.closest('input, textarea, select, [contenteditable="true"], [data-no-swipe]')) {
        return;
    }

    if (isOpen && !target.closest('#sidebar, .sidebar-backdrop')) {
        return;
    }

    if (!isOpen && target.closest('.table-wrap')) {
        return;
    }

    gesture = {
        startX: touch.clientX,
        startY: touch.clientY,
        startedAt: Date.now(),
        opening: !isOpen,
        horizontal: false,
    };
}, { passive: true });

document.addEventListener('touchmove', (event) => {
    if (!gesture || event.touches.length !== 1) {
        return;
    }

    const touch = event.touches[0];
    const dx = touch.clientX - gesture.startX;
    const dy = touch.clientY - gesture.startY;

    if (gesture.opening && dx > 0 && Math.abs(dx) > Math.abs(dy) * 1.2) {
        gesture.horizontal = true;
        if (event.cancelable) {
            event.preventDefault();
        }

        return;
    }

    if (!gesture.opening && dx < 0 && Math.abs(dx) > Math.abs(dy) * 1.2) {
        gesture.horizontal = true;
        if (event.cancelable) {
            event.preventDefault();
        }
    }
}, { passive: false });

document.addEventListener('touchend', (event) => {
    if (!gesture) {
        return;
    }

    const touch = event.changedTouches[0];
    const dx = touch.clientX - gesture.startX;
    const dy = Math.abs(touch.clientY - gesture.startY);
    const duration = Date.now() - gesture.startedAt;
    const wasHorizontal = gesture.horizontal;
    const opening = gesture.opening;

    resetGesture();

    if (!wasHorizontal || dy > 80 || duration > MAX_DURATION) {
        return;
    }

    const shouldOpen = opening && dx >= OPEN_DISTANCE;
    const shouldClose = !opening && dx <= -CLOSE_DISTANCE;

    if (!shouldOpen && !shouldClose) {
        return;
    }

    suppressClick = true;
    window.setTimeout(() => {
        suppressClick = false;
    }, 400);

    controlSidebar(shouldOpen);
}, { passive: true });

document.addEventListener('touchcancel', resetGesture, { passive: true });

document.addEventListener('click', (event) => {
    if (!suppressClick) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    suppressClick = false;
}, true);
