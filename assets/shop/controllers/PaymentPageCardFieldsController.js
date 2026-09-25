import { Controller } from '@hotwired/stimulus';
import { paymentPageSession } from '../scripts/paypal-payment-page';

const PAYMENT_SOURCE = 'card';

export default class extends Controller {
    static targets = ['form', 'loader', 'number', 'expiry', 'cvv', 'name'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        amount: String,
        createOrderUrl: String,
        completeOrderUrl: String,
        errorUrl: String,
        billingAddress: Object,
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

            if (!session.isEligible('advanced_cards')) {
                return;
            }

            this.cardSession = session.sdkInstance.createCardFieldsOneTimePaymentSession();
            this.mountFields();

            this.element.removeAttribute('hidden');
            this.formTarget.addEventListener('submit', (event) => {
                event.preventDefault();
                this.submit(session);
            });
        } catch (error) {
            console.error('PayPal Web SDK initialization error:', error);
        }
    }

    mountFields() {
        this.numberTarget.appendChild(this.cardSession.createCardFieldsComponent({ type: 'number' }));
        this.expiryTarget.appendChild(this.cardSession.createCardFieldsComponent({ type: 'expiry' }));
        this.cvvTarget.appendChild(this.cardSession.createCardFieldsComponent({ type: 'cvv' }));
        this.nameTarget.appendChild(this.cardSession.createCardFieldsComponent({ type: 'name' }));
    }

    async submit(session) {
        if (session.isBusy()) {
            return;
        }

        this.setSubmitting(true);

        let orderId = null;
        try {
            ({ orderId } = await session.startAttempt(PAYMENT_SOURCE));
            const { data, state } = await this.cardSession.submit(orderId, this.submitOptions());

            if (state === 'succeeded') {
                await this.complete(orderId);

                return;
            }

            if (state === 'canceled') {
                session.release();
                this.setSubmitting(false);

                return;
            }

            await this.reportError(data?.message ?? 'PayPal could not process the card payment.', orderId);
        } catch (error) {
            await this.reportError(String(error), orderId);
        }
    }

    async complete(payPalOrderId) {
        const response = await fetch(this.completeOrderUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ payPalOrderId }),
        });
        const details = await response.json();

        if (details.return_url) {
            window.location.href = details.return_url;

            return;
        }

        window.location.reload();
    }

    async reportError(message, payPalOrderId = null) {
        await fetch(this.errorUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ error: message, payPalOrderId }),
        });
        window.location.reload();
    }

    submitOptions() {
        if (!this.hasBillingAddressValue) {
            return {};
        }

        return { billingAddress: this.billingAddressValue };
    }

    setSubmitting(submitting) {
        const submitButton = this.formTarget.querySelector('button[type="submit"]');
        if (submitButton !== null) {
            submitButton.disabled = submitting;
        }

        if (this.hasLoaderTarget) {
            this.loaderTarget.hidden = !submitting;
        }
    }
}
