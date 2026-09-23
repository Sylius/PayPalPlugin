import { Controller } from '@hotwired/stimulus';
import { paymentPageSession } from '../scripts/paypal-payment-page';

export default class extends Controller {
    static targets = ['button', 'payLaterButton', 'venmoButton'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        amount: String,
        createOrderUrl: String,
        completeOrderUrl: String,
        cancelOrderUrl: String,
        errorUrl: String,
        payLaterEnabled: Boolean,
        venmoEnabled: Boolean,
    };

    async connect() {
        try {
            const session = await paymentPageSession({
                scriptUrl: this.scriptUrlValue,
                instanceConfig: this.instanceConfigValue,
                currencyCode: this.currencyCodeValue,
                amount: this.amountValue,
                createOrderUrl: this.createOrderUrlValue,
            });

            this.session = session;

            if (this.hasButtonTarget && session.isEligible('paypal')) {
                const paymentSession = session.sdkInstance.createPayPalOneTimePaymentSession(this.buildSessionOptions());

                this.buttonTarget.removeAttribute('hidden');
                this.buttonTarget.addEventListener('click', () => this.start(session, paymentSession));
            }

            if (this.payLaterEnabledValue && this.hasPayLaterButtonTarget && session.isEligible('paylater')) {
                const payLaterDetails = session.getDetails('paylater');
                this.payLaterButtonTarget.productCode = payLaterDetails.productCode;
                this.payLaterButtonTarget.countryCode = payLaterDetails.countryCode;

                const payLaterSession = session.sdkInstance.createPayLaterOneTimePaymentSession(this.buildSessionOptions());

                this.payLaterButtonTarget.removeAttribute('hidden');
                this.payLaterButtonTarget.addEventListener('click', () => this.start(session, payLaterSession));
            }

            if (this.venmoEnabledValue && this.hasVenmoButtonTarget && session.isEligible('venmo')) {
                const venmoSession = session.sdkInstance.createVenmoOneTimePaymentSession(this.buildSessionOptions());

                this.venmoButtonTarget.removeAttribute('hidden');
                this.venmoButtonTarget.addEventListener('click', () => this.start(session, venmoSession, 'venmo'));
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

    async start(session, paymentSession, paymentSource = null) {
        if (session.isBusy()) {
            return;
        }

        try {
            await paymentSession.start({ presentationMode: 'auto' }, session.startAttempt(paymentSource));
        } catch (error) {
            session.release();
            console.error('paymentSession.start() failed:', error);
        }
    }

    async onApprove(data) {
        const response = await fetch(this.completeOrderUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ payPalOrderId: data.orderId }),
        });
        const details = await response.json();

        if (details.return_url) {
            window.location.href = details.return_url;

            return;
        }

        window.location.reload();
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
            body: JSON.stringify({ error: String(error), payPalOrderId: this.session?.currentOrderId() ?? null }),
        });
        window.location.reload();
    }
}
