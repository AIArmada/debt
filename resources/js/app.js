const base64UrlToUint8Array = (value) => {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const normalized = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(normalized);

    return Uint8Array.from([...rawData].map((character) => character.charCodeAt(0)));
};

const registerPushSubscription = async (button) => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        button.textContent = 'Push is not supported in this browser';
        button.disabled = true;

        return;
    }

    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = 'Enabling push…';

    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            throw new Error('Notification permission was not granted.');
        }

        const registration = await navigator.serviceWorker.register('/service-worker.js');
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: base64UrlToUint8Array(button.dataset.pushPublicKey),
        });
        const response = await fetch(button.dataset.pushSubscribeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
            },
            body: JSON.stringify(subscription.toJSON()),
        });
        if (!response.ok) {
            throw new Error('The browser subscription could not be saved.');
        }

        button.textContent = 'This browser is enabled';
    } catch (error) {
        button.textContent = error instanceof Error ? error.message : 'Push could not be enabled';
        button.disabled = false;
        setTimeout(() => {
            button.textContent = originalText;
        }, 4500);
    }
};

const wirePushSubscriptionButton = () => {
    document.querySelectorAll('[data-push-subscribe]').forEach((button) => {
        if (button.dataset.pushWired === 'true') {
            return;
        }

        button.dataset.pushWired = 'true';
        button.addEventListener('click', () => registerPushSubscription(button));
    });
};

document.addEventListener('DOMContentLoaded', wirePushSubscriptionButton);
document.addEventListener('livewire:navigated', wirePushSubscriptionButton);
