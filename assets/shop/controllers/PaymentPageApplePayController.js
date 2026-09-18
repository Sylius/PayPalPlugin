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
        countryCode: String,
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
            this.countryCode = this.config.countryCode ?? this.countryCodeValue;

            if (!this.config.isEligible) {
                return;
            }

            if (this.countryCode === '') {
                console.error('Apple Pay needs a merchant country code, but neither the PayPal SDK nor the channel provided one. Fill in the shop billing data on the channel.');

                return;
            }

            this.element.removeAttribute('hidden');
            this.buttonTarget.addEventListener('click', () => this.start());
        } catch (error) {
            console.error('Apple Pay initialization error:', error);
        }
    }

    async start() {
        if (this.session.isBusy()) {
            return;
        }

        try {
            const sheet = new window.ApplePaySession(APPLE_PAY_VERSION, this.paymentRequest());

            sheet.onvalidatemerchant = (event) => this.validateMerchant(sheet, event);
            sheet.onpaymentauthorized = (event) => this.authorize(sheet, event);
            sheet.oncancel = () => this.session.release();

            sheet.begin();
        } catch (error) {
            await this.fail(error);
        }
    }

    paymentRequest() {
        return {
            countryCode: this.countryCode,
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
            await this.fail(error, `validationUrl=${event.validationURL}`);
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

    describe(error, context = null) {
        const described = [String(error)];

        for (const key of ['code', 'correlationId', 'debugId', 'debug_id']) {
            if (error?.[key] !== undefined) {
                described.push(`${key}=${error[key]}`);
            }
        }

        if (context !== null) {
            described.push(context);
        }

        return described.join(' | ');
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

    async fail(error, context = null) {
        console.error('Apple Pay payment failed:', error);

        await fetch(this.errorUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({
                error: this.describe(error, context),
                payPalOrderId: this.session?.currentOrderId() ?? null,
            }),
        });

        this.session.release();
        window.location.reload();
    }
}
