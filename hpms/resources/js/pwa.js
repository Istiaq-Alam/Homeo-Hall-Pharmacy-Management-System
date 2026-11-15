const installButton = document.getElementById('install-app');
const installDialog = document.getElementById('install-dialog');
const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent)
    || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
const isIosSafari = isIos
    && /safari/i.test(window.navigator.userAgent)
    && !/crios|fxios|edgios/i.test(window.navigator.userAgent);

let deferredPrompt = null;

const hideInstallButton = () => {
    if (installButton) {
        installButton.hidden = true;
    }
};

const showIosInstructions = () => {
    if (!installDialog) {
        return;
    }

    if (typeof installDialog.showModal === 'function') {
        installDialog.showModal();
    } else {
        window.alert(window.HPMS_LOCALE?.messages?.install_share || 'Tap the Share button in Safari, then choose “Add to Home Screen”.');
    }
};

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;

    if (!isStandalone() && installButton) {
        installButton.hidden = false;
        delete installButton.dataset.ios;
    }
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    hideInstallButton();
    installDialog?.close();
});

if (isStandalone()) {
    hideInstallButton();
} else if (isIosSafari && installButton) {
    installButton.hidden = false;
    installButton.dataset.ios = 'true';
}

installButton?.addEventListener('click', async () => {
    if (installButton.dataset.ios === 'true') {
        showIosInstructions();

        return;
    }

    if (!deferredPrompt) {
        return;
    }

    deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;
    deferredPrompt = null;

    if (choice.outcome === 'accepted') {
        hideInstallButton();
    }
});

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // Offline support is an enhancement; the app remains fully usable without it.
        });
    }, { once: true });
}
