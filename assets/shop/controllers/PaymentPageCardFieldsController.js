import { Controller } from '@hotwired/stimulus';
import { paymentPageSession } from '../scripts/paypal-payment-page';

const PAYMENT_SOURCE = 'card';

const REQUIRED_FIELDS = ['number', 'expiry', 'cvv'];

export default class extends Controller {
    static targets = ['form', 'loader', 'number', 'expiry', 'cvv', 'name', 'invalid'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        amount: String,
        createOrderUrl: String,
        errorUrl: String,
        billingAddress: Object,
    };

    fieldsState = null;

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
            for (const eventName of ['change', 'validitychange']) {
                this.cardSession.on(eventName, ({ data }) => {
                    this.fieldsState = data;
                });
            }

            this.session = session;
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

        const invalidFields = REQUIRED_FIELDS.filter((field) => !this.fieldsState?.[field]?.isValid);
        this.markInvalidFields(invalidFields);
        if (invalidFields.length > 0) {
            return;
        }

        this.setSubmitting(true);

        let orderId = null;
        try {
            ({ orderId } = await session.startAttempt(PAYMENT_SOURCE));
            const { data, state } = await this.cardSession.submit(orderId, this.submitOptions());

            if (state === 'succeeded' || (state === 'failed' && data?.liabilityShift)) {
                this.complete();

                return;
            }

            if (state === 'canceled') {
                this.cancel();

                return;
            }

            await this.reportError(data?.message ?? 'PayPal could not process the card payment.', orderId);
        } catch (error) {
            await this.reportError(String(error), orderId);
        }
    }

    complete() {
        window.location.href = this.session.currentApproveUrl();
    }

    cancel() {
        this.session.release();
        this.setSubmitting(false);
    }

    async reportError(message, payPalOrderId = null) {
        if (this.session.currentApproveUrl()) {
            console.error('PayPal card payment failed:', message);
            window.location.href = this.session.currentApproveUrl();

            return;
        }

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

    markInvalidFields(invalidFields) {
        for (const field of REQUIRED_FIELDS) {
            this[`${field}Target`].classList.toggle('is-invalid', invalidFields.includes(field));
        }

        if (this.hasInvalidTarget) {
            this.invalidTarget.hidden = invalidFields.length === 0;
        }
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
