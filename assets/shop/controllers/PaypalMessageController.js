import { Controller } from '@hotwired/stimulus';
import { loadWebSdkOnce } from '../scripts/paypal-web-sdk';

export default class extends Controller {
    static values = {
        amount: Number,
        currencyCode: String,
        scriptUrl: String,
        instanceConfig: Object,
    };

    connect() {
        this.init();
    }

    async init() {
        try {
            await loadWebSdkOnce(this.scriptUrlValue);

            await customElements.whenDefined('paypal-message');
            await this.element.updateComplete;
            this.element.amount = this.amountValue;
            this.element.currencyCode = this.currencyCodeValue;

            const sdkInstance = await window.paypal.createInstance(this.instanceConfigValue);
            const messagesInstance = sdkInstance.createPayPalMessages();

            await messagesInstance.fetchContent(this.element.getFetchContentOptions());
        } catch (error) {
            console.error('PayPal Pay Later messaging initialization error:', error);
        }
    }
}
