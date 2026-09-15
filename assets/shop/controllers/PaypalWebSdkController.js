import { Controller } from '@hotwired/stimulus';
import { loadWebSdkOnce } from '../scripts/paypal-web-sdk';

export default class extends Controller {
    static targets = ['paypalButton'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        createOrderUrl: String,
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
            await loadWebSdkOnce(this.scriptUrlValue);

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

            const paymentSession = sdkInstance.createPayPalOneTimePaymentSession({
                onApprove: this.onApprove.bind(this),
                onCancel: this.onCancel.bind(this),
                onError: this.onError.bind(this),
            });

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
