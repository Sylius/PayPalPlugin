import { Controller } from '@hotwired/stimulus';
import { loadWebSdkOnce } from '../scripts/paypal-web-sdk';

export default class extends Controller {
    static values = {
        amount: String,
        currencyCode: String,
        scriptUrl: String,
        instanceConfig: Object,
    };

    initialized = false;

    async connect() {
        try {
            await loadWebSdkOnce(this.scriptUrlValue);

            this.sdkInstance = await window.paypal.createInstance(this.instanceConfigValue);
            this.messagesInstance = this.sdkInstance.createPayPalMessages();

            await customElements.whenDefined('paypal-message');
            await this.element.updateComplete;

            await this.refresh();
        } catch (error) {
            console.error('PayPal Pay Later messaging initialization error:', error);
            this.element.setAttribute('hidden', '');
        } finally {
            this.initialized = true;
        }
    }

    amountValueChanged() {
        if (!this.initialized) {
            return;
        }

        this.refresh();
    }

    currencyCodeValueChanged() {
        if (!this.initialized) {
            return;
        }

        this.refresh();
    }

    async refresh() {
        try {
            const paymentMethods = await this.sdkInstance.findEligibleMethods({
                currencyCode: this.currencyCodeValue,
                amount: this.amountValue,
            });

            if (!paymentMethods.isEligible('paylater')) {
                this.element.setAttribute('hidden', '');

                return;
            }

            this.element.amount = this.amountValue;
            this.element.currencyCode = this.currencyCodeValue;

            await this.messagesInstance.fetchContent(this.element.getFetchContentOptions());

            this.element.removeAttribute('hidden');
        } catch (error) {
            console.error('PayPal Pay Later messaging refresh error:', error);
            this.element.setAttribute('hidden', '');
        }
    }
}
