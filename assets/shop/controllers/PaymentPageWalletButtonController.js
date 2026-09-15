import { Controller } from '@hotwired/stimulus';
import { paymentPageSession } from '../scripts/paypal-payment-page';

export default class extends Controller {
    static targets = ['button'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        createOrderUrl: String,
        completeOrderUrl: String,
        cancelOrderUrl: String,
        errorUrl: String,
    };

    async connect() {
        try {
            const session = await paymentPageSession({
                scriptUrl: this.scriptUrlValue,
                instanceConfig: this.instanceConfigValue,
                currencyCode: this.currencyCodeValue,
                createOrderUrl: this.createOrderUrlValue,
            });

            if (!session.isEligible('paypal')) {
                return;
            }

            const paymentSession = session.sdkInstance.createPayPalOneTimePaymentSession({
                onApprove: this.onApprove.bind(this),
                onCancel: this.onCancel.bind(this),
                onError: this.onError.bind(this),
            });

            this.buttonTarget.removeAttribute('hidden');
            this.buttonTarget.addEventListener('click', () => this.start(session, paymentSession));
        } catch (error) {
            console.error('PayPal Web SDK initialization error:', error);
        }
    }

    async start(session, paymentSession) {
        if (session.isBusy()) {
            return;
        }

        try {
            await paymentSession.start({ presentationMode: 'auto' }, session.startAttempt());
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
        await fetch(this.errorUrlValue, { method: 'post', body: String(error) });
        window.location.reload();
    }
}
