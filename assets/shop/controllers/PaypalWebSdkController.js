import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['paypalButton', 'payLaterButton', 'venmoButton'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        amount: String,
        createOrderUrl: String,
        addToCartFormSelector: String,
        captureOrderUrl: String,
        cancelOrderUrl: String,
        errorUrl: String,
        loadingSelector: String,
        payLaterEnabled: Boolean,
        venmoEnabled: Boolean,
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
                testBuyerCountry: this.instanceConfigValue.testBuyerCountry,
            });

            const eligibilityRequest = { currencyCode: this.currencyCodeValue };
            if (this.hasAmountValue && this.amountValue !== '') {
                eligibilityRequest.amount = this.amountValue;
            }
            const paymentMethods = await sdkInstance.findEligibleMethods(eligibilityRequest);

            if (paymentMethods.isEligible('paypal')) {
                this.wireUpButton(this.paypalButtonTarget, sdkInstance.createPayPalOneTimePaymentSession(this.buildSessionOptions()));
            }

            if (this.payLaterEnabledValue && this.hasPayLaterButtonTarget && paymentMethods.isEligible('paylater')) {
                const payLaterDetails = paymentMethods.getDetails('paylater');
                this.payLaterButtonTarget.productCode = payLaterDetails.productCode;
                this.payLaterButtonTarget.countryCode = payLaterDetails.countryCode;
                this.wireUpButton(this.payLaterButtonTarget, sdkInstance.createPayLaterOneTimePaymentSession(this.buildSessionOptions()));
            }

            if (this.venmoEnabledValue && this.hasVenmoButtonTarget && paymentMethods.isEligible('venmo')) {
                this.wireUpButton(this.venmoButtonTarget, sdkInstance.createVenmoOneTimePaymentSession(this.buildSessionOptions()));
            }
        } catch (error) {
            console.error('PayPal Web SDK initialization error:', error);
        }
    }

    buildSessionOptions() {
        return {
            onApprove: this.onApprove.bind(this),
            onCancel: this.onCancel.bind(this),
            onError: this.onError.bind(this),
        };
    }

    wireUpButton(buttonTarget, paymentSession) {
        buttonTarget.removeAttribute('hidden');
        buttonTarget.addEventListener('click', async () => {
            try {
                await paymentSession.start({ presentationMode: 'auto' }, this.createOrder());
            } catch (error) {
                console.error('paymentSession.start() failed:', error);
            }
        });
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
