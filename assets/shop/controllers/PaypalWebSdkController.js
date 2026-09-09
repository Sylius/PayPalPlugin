import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['paypalButton'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        createOrderUrl: String,
        updateOrderUrl: String,
        availableCountries: Array,
        addToCartFormSelector: String,
        captureOrderUrl: String,
        cancelOrderUrl: String,
        errorUrl: String,
        loadingSelector: String,
    };

    syliusOrderId = null;

    connect() {
        this.init();
    }

    async init() {
        try {
            await this.loadWebSdkOnce();

            const sdkInstance = await window.paypal.createInstance({
                clientId: this.instanceConfigValue.clientId,
                components: this.instanceConfigValue.components,
                pageType: this.instanceConfigValue.pageType,
                partnerAttributionId: this.instanceConfigValue.partnerAttributionId,
            });

            const paymentMethods = await sdkInstance.findEligibleMethods({ currencyCode: this.currencyCodeValue });
            if (!paymentMethods.isEligible('paypal')) {
                return;
            }

            const sessionOptions = {
                onApprove: this.onApprove.bind(this),
                onCancel: this.onCancel.bind(this),
                onError: this.onError.bind(this),
            };

            if (this.hasUpdateOrderUrlValue && this.updateOrderUrlValue !== '') {
                sessionOptions.onShippingAddressChange = this.onShippingAddressChange.bind(this);
            }

            const paymentSession = sdkInstance.createPayPalOneTimePaymentSession(sessionOptions);

            this.paypalButtonTarget.removeAttribute('hidden');
            this.paypalButtonTarget.addEventListener('click', async () => {
                try {
                    await paymentSession.start({ presentationMode: 'auto' }, this.createOrder());
                } catch (error) {
                    console.error('paymentSession.start() failed:', error);
                }
            });
        } catch (error) {
            console.error('PayPal Web SDK initialization error:', error);
        }
    }

    loadWebSdkOnce() {
        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-paypal-web-sdk]');
            if (existing !== null) {
                window.paypal ? resolve() : existing.addEventListener('load', resolve);

                return;
            }

            const script = document.createElement('script');
            script.src = this.scriptUrlValue;
            script.async = true;
            script.dataset.paypalWebSdk = 'true';
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    async createOrder() {
        const requestInit = { method: 'post' };
        if (this.hasAddToCartFormSelectorValue && this.addToCartFormSelectorValue !== '') {
            requestInit.body = new FormData(document.querySelector(this.addToCartFormSelectorValue));
        }

        const response = await fetch(this.createOrderUrlValue, requestInit);

        if (this.hasLoadingSelectorValue && this.loadingSelectorValue !== '') {
            document.querySelector(this.loadingSelectorValue)?.style.setProperty('display', 'block');
        }

        if (response.status === 400) {
            window.location.reload();

            return;
        }

        const data = await response.json();
        this.syliusOrderId = data.id;

        return { orderId: data.orderId };
    }

    /**
     * The v6 counterpart of the v5 buttons' onShippingChange: PayPal calls it in the browser whenever
     * the buyer picks or changes their shipping address inside the wallet, and the order total has to be
     * brought in line with that address before they approve - otherwise the amount PayPal captures no
     * longer matches the Sylius order, and ProcessPayPalOrderAction rejects the payment. Throwing makes
     * PayPal reject the address and ask the buyer for another one.
     */
    async onShippingAddressChange(data) {
        const shippingAddress = data.shippingAddress ?? {};

        if (!this.availableCountriesValue.includes(shippingAddress.countryCode)) {
            throw new Error(data.errors.COUNTRY_ERROR);
        }

        const response = await fetch(this.updateOrderUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({
                orderID: data.orderId,
                shipping_address: {
                    city: shippingAddress.city,
                    state: shippingAddress.state,
                    postal_code: shippingAddress.postalCode,
                    country_code: shippingAddress.countryCode,
                },
            }),
        });

        if (!response.ok) {
            throw new Error(data.errors.ADDRESS_ERROR);
        }
    }

    async onApprove(data) {
        const response = await fetch(this.captureOrderUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ payPalOrderId: data.orderId, orderId: this.syliusOrderId }),
        });
        const details = await response.json();
        window.location.href = details.return_url;
    }

    async onCancel(data) {
        await fetch(this.cancelOrderUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ payPalOrderId: data.orderId }),
        });
        window.location.reload();
    }

    async onError(error) {
        await fetch(this.errorUrlValue, { method: 'post', headers: {}, body: error });
        window.location.reload();
    }
}
