const APPLE_PAY_SDK_URL = 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js';

export function loadApplePaySdkOnce() {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-apple-pay-sdk]');
        if (existing !== null) {
            window.customElements?.get('apple-pay-button') ? resolve() : existing.addEventListener('load', resolve);

            return;
        }

        const script = document.createElement('script');
        script.src = APPLE_PAY_SDK_URL;
        script.async = true;
        script.crossOrigin = 'anonymous';
        script.dataset.applePaySdk = 'true';
        script.onload = resolve;
        script.onerror = reject;
        document.body.appendChild(script);
    });
}
