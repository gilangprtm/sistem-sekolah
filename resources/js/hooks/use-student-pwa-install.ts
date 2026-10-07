import { useSyncExternalStore } from 'react';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

type InstallSnapshot = {
    canInstall: boolean;
    isDismissed: boolean;
    isIos: boolean;
    isInstalled: boolean;
};

const DISMISSED_KEY = 'student-pwa-install-dismissed';
const listeners = new Set<() => void>();
let deferredPrompt: BeforeInstallPromptEvent | null = null;
let initialized = false;
let snapshot: InstallSnapshot = {
    canInstall: false,
    isDismissed: false,
    isIos: false,
    isInstalled: false,
};

function notify(): void {
    listeners.forEach((listener) => listener());
}

function isIosDevice(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return (
        /iphone|ipad|ipod/i.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

function isStandalone(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        ('standalone' in navigator &&
            Boolean(
                (navigator as Navigator & { standalone?: boolean }).standalone,
            ))
    );
}

function wasDismissed(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(DISMISSED_KEY) === 'true';
    } catch {
        return false;
    }
}

function setDismissed(): void {
    try {
        window.localStorage.setItem(DISMISSED_KEY, 'true');
    } catch {
        // The in-memory state still prevents a prompt loop for this session.
    }
}

function updateSnapshot(patch: Partial<InstallSnapshot>): void {
    const nextSnapshot = { ...snapshot, ...patch };

    if (
        nextSnapshot.canInstall === snapshot.canInstall &&
        nextSnapshot.isDismissed === snapshot.isDismissed &&
        nextSnapshot.isIos === snapshot.isIos &&
        nextSnapshot.isInstalled === snapshot.isInstalled
    ) {
        return;
    }

    snapshot = nextSnapshot;
    notify();
}

function handleBeforeInstallPrompt(event: Event): void {
    event.preventDefault();

    if (snapshot.isDismissed || snapshot.isInstalled) {
        return;
    }

    deferredPrompt = event as BeforeInstallPromptEvent;
    updateSnapshot({ canInstall: true });
}

function handleAppInstalled(): void {
    deferredPrompt = null;
    updateSnapshot({ canInstall: false, isInstalled: true });
}

export function initializeStudentPwaInstall(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const standalone = isStandalone();

    updateSnapshot({
        isDismissed: wasDismissed(),
        isInstalled: standalone,
        isIos: isIosDevice(),
    });

    if (initialized) {
        return;
    }

    initialized = true;
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleAppInstalled);
}

export function subscribeToStudentPwaInstall(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function getStudentPwaInstallSnapshot(): InstallSnapshot {
    return snapshot;
}

export async function installStudentPwa(): Promise<void> {
    if (deferredPrompt === null) {
        return;
    }

    const prompt = deferredPrompt;
    deferredPrompt = null;
    updateSnapshot({ canInstall: false });
    await prompt.prompt();

    const choice = await prompt.userChoice;

    if (choice.outcome === 'accepted') {
        updateSnapshot({ isInstalled: true });
    } else {
        dismissStudentPwaInstall();
    }
}

export function dismissStudentPwaInstall(): void {
    deferredPrompt = null;
    setDismissed();
    updateSnapshot({ canInstall: false, isDismissed: true });
}

export function useStudentPwaInstall(): InstallSnapshot {
    initializeStudentPwaInstall();

    return useSyncExternalStore(
        subscribeToStudentPwaInstall,
        getStudentPwaInstallSnapshot,
        getStudentPwaInstallSnapshot,
    );
}
