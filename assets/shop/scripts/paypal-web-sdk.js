export function loadWebSdkOnce(scriptUrl) {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-paypal-web-sdk]');
        if (existing !== null) {
            if (existing.src !== new URL(scriptUrl, document.baseURI).href) {
                reject(new Error(`The PayPal Web SDK has already been loaded from ${existing.src}, it cannot be loaded from ${scriptUrl} on the same page.`));

                return;
            }

            window.paypal ? resolve() : existing.addEventListener('load', resolve);

            return;
        }

        const script = document.createElement('script');
        script.src = scriptUrl;
        script.async = true;
        script.dataset.paypalWebSdk = 'true';
        script.onload = resolve;
        script.onerror = reject;
        document.body.appendChild(script);
    });
}
