import { Controller } from '@hotwired/stimulus';
import { paymentPageSession } from '../scripts/paypal-payment-page';
import { loadApplePaySdkOnce } from '../scripts/apple-pay';

const PAYMENT_SOURCE = 'apple_pay';

const APPLE_PAY_VERSION = 4;

export default class extends Controller {
    static targets = ['button'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        amount: String,
        storeName: String,
        createOrderUrl: String,
        completeOrderUrl: String,
        errorUrl: String,
    };

    async connect() {
        if (!window.ApplePaySession?.supportsVersion(APPLE_PAY_VERSION) || !window.ApplePaySession.canMakePayments()) {
            return;
        }

        try {
            const [session] = await Promise.all([
                paymentPageSession({
                    scriptUrl: this.scriptUrlValue,
                    instanceConfig: this.instanceConfigValue,
                    currencyCode: this.currencyCodeValue,
                    createOrderUrl: this.createOrderUrlValue,
                }),
                loadApplePaySdkOnce(),
            ]);

            this.session = session;
            this.applePaySession = session.sdkInstance.createApplePayOneTimePaymentSession();
            this.config = await this.applePaySession.config();

            if (!this.config.isEligible) {
                return;
            }

            this.element.removeAttribute('hidden');
            this.buttonTarget.addEventListener('click', () => this.start());
        } catch (error) {
            console.error('Apple Pay initialization error:', error);
        }
    }

    start() {
        if (this.session.isBusy()) {
            return;
        }

        const sheet = new window.ApplePaySession(APPLE_PAY_VERSION, this.paymentRequest());

        sheet.onvalidatemerchant = (event) => this.validateMerchant(sheet, event);
        sheet.onpaymentauthorized = (event) => this.authorize(sheet, event);
        sheet.oncancel = () => this.session.release();

        sheet.begin();
    }

    paymentRequest() {
        return {
            countryCode: this.config.countryCode,
            currencyCode: this.currencyCodeValue,
            merchantCapabilities: this.config.merchantCapabilities,
            supportedNetworks: this.config.supportedNetworks,
            requiredBillingContactFields: ['postalAddress', 'name'],
            total: { label: this.storeNameValue, amount: this.amountValue, type: 'final' },
        };
    }

    async validateMerchant(sheet, event) {
        try {
            const { merchantSession } = await this.applePaySession.validateMerchant({
                validationUrl: event.validationURL,
            });

            sheet.completeMerchantValidation(merchantSession);
        } catch (error) {
            sheet.abort();
            await this.fail(error);
        }
    }

    async authorize(sheet, event) {
        try {
            const { orderId } = await this.session.startAttempt(PAYMENT_SOURCE);
            const { status } = await this.applePaySession.confirmOrder({
                orderId,
                token: event.payment.token,
                billingContact: event.payment.billingContact,
                shippingContact: event.payment.shippingContact,
            });

            if (status === 'PAYER_ACTION_REQUIRED') {
                throw new Error('Apple Pay payment requires an additional buyer action, which is not supported yet.');
            }

            const returnUrl = await this.complete(orderId);

            sheet.completePayment({ status: window.ApplePaySession.STATUS_SUCCESS });
            window.location.href = returnUrl ?? window.location.href;
        } catch (error) {
            sheet.completePayment({ status: window.ApplePaySession.STATUS_FAILURE });
            await this.fail(error);
        }
    }

    async complete(payPalOrderId) {
        const response = await fetch(this.completeOrderUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ payPalOrderId }),
        });
        const details = await response.json();

        return details.return_url ?? null;
    }

    async fail(error) {
        console.error('Apple Pay payment failed:', error);

        await fetch(this.errorUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({
                error: String(error),
                payPalOrderId: this.session?.currentOrderId() ?? null,
            }),
        });

        this.session.release();
        window.location.reload();
    }
}
