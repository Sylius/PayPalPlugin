import { loadWebSdkOnce } from './paypal-web-sdk';

const PAYMENT_SOURCE_NOT_AVAILABLE = 'payment_source_not_available';

export class PaymentSourceNotAvailableError extends Error {}

let session = null;
let busy = false;
let attemptOrderId = null;
let attemptApproveUrl = null;

export function isBusy() {
    return busy;
}

export function release() {
    busy = false;
}

export function currentOrderId() {
    return attemptOrderId;
}

export function currentApproveUrl() {
    return attemptApproveUrl;
}

export async function startAttempt(createOrderUrl, paymentSource = null) {
    busy = true;
    attemptOrderId = null;
    attemptApproveUrl = null;

    const response = await fetch(createOrderUrl, {
        method: 'post',
        ...(paymentSource === null ? {} : {
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ paymentSource }),
        }),
    });
    if (await isPaymentSourceNotAvailable(response)) {
        busy = false;

        throw new PaymentSourceNotAvailableError('The selected payment source is not available.');
    }

    if (!response.ok) {
        busy = false;

        throw new Error(`Could not start a PayPal payment attempt (${response.status}).`);
    }

    const data = await response.json();
    attemptOrderId = data.paypal_order_id ?? null;
    attemptApproveUrl = data.approve_url ?? null;

    return {
        orderId: attemptOrderId,
        approveUrl: attemptApproveUrl,
        payerActionUrl: data.payer_action_url ?? null,
    };
}

async function isPaymentSourceNotAvailable(response) {
    if (response.status !== 422) {
        return false;
    }

    const data = await response.json().catch(() => ({}));

    return data.error === PAYMENT_SOURCE_NOT_AVAILABLE;
}

export function paymentPageSession(config) {
    session ??= createSession(config);

    return session;
}

async function createSession({ scriptUrl, instanceConfig, currencyCode, amount, createOrderUrl }) {
    await loadWebSdkOnce(scriptUrl);

    const sdkInstance = await window.paypal.createInstance(instanceConfig);
    const eligibilityRequest = { currencyCode };
    if (amount) {
        eligibilityRequest.amount = amount;
    }
    const eligibleMethods = await sdkInstance.findEligibleMethods(eligibilityRequest);

    return {
        sdkInstance,

        isEligible: (fundingSource) => eligibleMethods.isEligible(fundingSource),

        getDetails: (fundingSource) => eligibleMethods.getDetails(fundingSource),

        isBusy,

        currentOrderId,

        currentApproveUrl,

        release,

        startAttempt: async (paymentSource = null) => {
            const { orderId } = await startAttempt(createOrderUrl, paymentSource);

            return { orderId };
        },
    };
}
