const GOOGLE_PAY_SDK_URL = 'https://pay.google.com/gp/p/js/pay.js';

export function loadGooglePaySdkOnce() {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-google-pay-sdk]');
        if (existing !== null) {
            window.google?.payments ? resolve() : existing.addEventListener('load', resolve);

            return;
        }

        const script = document.createElement('script');
        script.src = GOOGLE_PAY_SDK_URL;
        script.async = true;
        script.dataset.googlePaySdk = 'true';
        script.onload = resolve;
        script.onerror = reject;
        document.body.appendChild(script);
    });
}
