import { Controller } from '@hotwired/stimulus';
import { loadWebSdkOnce } from '../scripts/paypal-web-sdk';

let sdkInstancePromise = null;

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

    payPalOrderId = null;

    initialized = false;

    wiredTargets = new Set();

    async connect() {
        try {
            await loadWebSdkOnce(this.scriptUrlValue);

            sdkInstancePromise ??= window.paypal.createInstance(this.instanceConfigValue);
            this.sdkInstance = await sdkInstancePromise;

            await this.refreshEligibility();
        } catch (error) {
            console.error('PayPal Web SDK initialization error:', error);
        } finally {
            this.initialized = true;
        }
    }

    amountValueChanged() {
        if (!this.initialized) {
            return;
        }

        this.refreshEligibility();
    }

    currencyCodeValueChanged() {
        if (!this.initialized) {
            return;
        }

        this.refreshEligibility();
    }

    async refreshEligibility() {
        const eligibilityRequest = { currencyCode: this.currencyCodeValue };
        if (this.hasAmountValue && this.amountValue !== '') {
            eligibilityRequest.amount = this.amountValue;
        }

        let paymentMethods;
        try {
            paymentMethods = await this.sdkInstance.findEligibleMethods(eligibilityRequest);
        } catch (error) {
            console.error('PayPal eligibility check error:', error);

            return;
        }

        try {
            if (!this.wiredTargets.has('paypal') && this.hasPaypalButtonTarget && paymentMethods.isEligible('paypal')) {
                this.wiredTargets.add('paypal');
                this.wireUpButton(this.paypalButtonTarget, this.sdkInstance.createPayPalOneTimePaymentSession(this.buildSessionOptions()));
            }
        } catch (error) {
            console.error('PayPal button setup error:', error);
        }

        try {
            if (
                !this.wiredTargets.has('paylater') &&
                this.payLaterEnabledValue &&
                this.hasPayLaterButtonTarget &&
                paymentMethods.isEligible('paylater')
            ) {
                this.wiredTargets.add('paylater');
                const payLaterDetails = paymentMethods.getDetails('paylater');
                this.payLaterButtonTarget.productCode = payLaterDetails.productCode;
                this.payLaterButtonTarget.countryCode = payLaterDetails.countryCode;
                this.wireUpButton(this.payLaterButtonTarget, this.sdkInstance.createPayLaterOneTimePaymentSession(this.buildSessionOptions()));
            }
        } catch (error) {
            console.error('Pay Later button setup error:', error);
        }

        try {
            if (
                !this.wiredTargets.has('venmo') &&
                this.venmoEnabledValue &&
                this.hasVenmoButtonTarget &&
                paymentMethods.isEligible('venmo')
            ) {
                this.wiredTargets.add('venmo');
                this.wireUpButton(this.venmoButtonTarget, this.sdkInstance.createVenmoOneTimePaymentSession(this.buildSessionOptions()), 'venmo');
            }
        } catch (error) {
            console.error('Venmo button setup error:', error);
        }
    }

    buildSessionOptions() {
        return {
            onApprove: this.onApprove.bind(this),
            onCancel: this.onCancel.bind(this),
            onError: this.onError.bind(this),
        };
    }

    wireUpButton(buttonTarget, paymentSession, paymentSource = null) {
        buttonTarget.removeAttribute('hidden');
        buttonTarget.addEventListener('click', async () => {
            try {
                await paymentSession.start({ presentationMode: 'auto' }, this.createOrder(paymentSource));
            } catch (error) {
                console.error('paymentSession.start() failed:', error);
            }
        });
    }

    async createOrder(paymentSource = null) {
        const requestInit = { method: 'post' };
        if (this.hasAddToCartFormSelectorValue && this.addToCartFormSelectorValue !== '') {
            requestInit.body = new FormData(document.querySelector(this.addToCartFormSelectorValue));
        }

        const url = new URL(this.createOrderUrlValue, window.location.origin);
        if (paymentSource !== null) {
            url.searchParams.set('paymentSource', paymentSource);
        }

        const response = await fetch(url, requestInit);

        if (this.hasLoadingSelectorValue && this.loadingSelectorValue !== '') {
            document.querySelector(this.loadingSelectorValue)?.style.setProperty('display', 'block');
        }

        if (!response.ok) {
            window.location.reload();

            return;
        }

        const data = await response.json();
        this.syliusOrderId = data.id;
        this.payPalOrderId = data.orderId;

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
        await fetch(this.errorUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ error: String(error), payPalOrderId: this.payPalOrderId }),
        });
        window.location.reload();
    }
}
