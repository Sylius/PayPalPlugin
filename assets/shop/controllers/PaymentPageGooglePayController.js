import { Controller } from '@hotwired/stimulus';
import { paymentPageSession } from '../scripts/paypal-payment-page';
import { loadGooglePaySdkOnce } from '../scripts/google-pay';

const PAYMENT_SOURCE = 'google_pay';

export default class extends Controller {
    static targets = ['button'];

    static values = {
        scriptUrl: String,
        instanceConfig: Object,
        currencyCode: String,
        amount: String,
        languageCode: String,
        sandbox: Boolean,
        createOrderUrl: String,
        completeOrderUrl: String,
        errorUrl: String,
    };

    returnUrl = null;

    reported = false;

    async connect() {
        try {
            const [session] = await Promise.all([
                paymentPageSession({
                    scriptUrl: this.scriptUrlValue,
                    instanceConfig: this.instanceConfigValue,
                    currencyCode: this.currencyCodeValue,
                    createOrderUrl: this.createOrderUrlValue,
                }),
                loadGooglePaySdkOnce(),
            ]);

            this.session = session;
            this.googlePaySession = session.sdkInstance.createGooglePayOneTimePaymentSession();

            const config = await this.googlePaySession.getGooglePayConfig();
            const paymentsClient = new window.google.payments.api.PaymentsClient({
                environment: this.sandboxValue ? 'TEST' : 'PRODUCTION',
                paymentDataCallbacks: { onPaymentAuthorized: this.onPaymentAuthorized.bind(this) },
            });

            const { result } = await paymentsClient.isReadyToPay({
                allowedPaymentMethods: config.allowedPaymentMethods,
                apiVersion: config.apiVersion,
                apiVersionMinor: config.apiVersionMinor,
            });

            if (!result) {
                return;
            }

            this.buttonTarget.appendChild(paymentsClient.createButton({
                onClick: () => this.start(paymentsClient, config),
                buttonSizeMode: 'fill',
                ...(this.languageCodeValue === '' ? {} : { buttonLocale: this.languageCodeValue }),
            }));
        } catch (error) {
            console.error('Google Pay initialization error:', error);
        }
    }

    async start(paymentsClient, config) {
        if (this.session.isBusy()) {
            return;
        }

        try {
            await paymentsClient.loadPaymentData(this.paymentDataRequest(config));

            window.location.href = this.returnUrl ?? window.location.href;
        } catch (error) {
            if (this.reported) {
                window.location.reload();

                return;
            }

            this.session.release();
            console.error('Google Pay sheet failed:', error);
        }
    }

    paymentDataRequest(config) {
        return {
            apiVersion: config.apiVersion,
            apiVersionMinor: config.apiVersionMinor,
            allowedPaymentMethods: config.allowedPaymentMethods,
            merchantInfo: config.merchantInfo,
            transactionInfo: {
                countryCode: config.countryCode,
                currencyCode: this.currencyCodeValue,
                totalPriceStatus: 'FINAL',
                totalPrice: this.amountValue,
            },
            callbackIntents: ['PAYMENT_AUTHORIZATION'],
        };
    }

    async onPaymentAuthorized(paymentData) {
        try {
            const { orderId } = await this.session.startAttempt(PAYMENT_SOURCE);
            const { status } = await this.googlePaySession.confirmOrder({
                orderId,
                paymentMethodData: paymentData.paymentMethodData,
            });

            if (status === 'PAYER_ACTION_REQUIRED') {
                throw new Error('Google Pay payment requires an additional buyer action, which is not supported yet.');
            }

            this.returnUrl = await this.complete(orderId);

            return { transactionState: 'SUCCESS' };
        } catch (error) {
            this.session.release();
            await this.reportError(error);
            this.reported = true;

            return { transactionState: 'ERROR', error: { message: String(error) } };
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

    async reportError(error) {
        await fetch(this.errorUrlValue, {
            method: 'post',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({
                error: String(error),
                payPalOrderId: this.session?.currentOrderId() ?? null,
            }),
        });
    }
}
