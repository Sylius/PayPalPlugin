import { Controller } from '@hotwired/stimulus';
import { isBusy, release, startAttempt } from '../scripts/paypal-payment-page';

export default class extends Controller {
    static values = {
        paymentSource: String,
        createOrderUrl: String,
        errorUrl: String,
    };

    async start(event) {
        event.preventDefault();

        if (isBusy()) {
            return;
        }

        try {
            const { payerActionUrl } = await startAttempt(this.createOrderUrlValue, this.paymentSourceValue);

            if (!payerActionUrl) {
                throw new Error(`PayPal sent no link to approve the ${this.paymentSourceValue} payment at.`);
            }

            window.location.href = payerActionUrl;
        } catch (error) {
            release();
            await this.reportError(error);
            window.location.reload();
        }
    }

    async reportError(error) {
        await fetch(this.errorUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ error: String(error) }),
        });
    }
}
