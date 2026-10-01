import { loadWebSdkOnce } from './paypal-web-sdk';

let session = null;
let busy = false;
let attemptOrderId = null;

export function isBusy() {
    return busy;
}

export function release() {
    busy = false;
}

export function currentOrderId() {
    return attemptOrderId;
}

export async function startAttempt(createOrderUrl, paymentSource = null) {
    busy = true;
    attemptOrderId = null;

    const response = await fetch(createOrderUrl, {
        method: 'post',
        ...(paymentSource === null ? {} : {
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ paymentSource }),
        }),
    });
    if (!response.ok) {
        busy = false;

        throw new Error(`Could not start a PayPal payment attempt (${response.status}).`);
    }

    const data = await response.json();
    attemptOrderId = data.orderId;

    return data;
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

        release,

        startAttempt: async (paymentSource = null) => {
            const { orderId } = await startAttempt(createOrderUrl, paymentSource);

            return { orderId };
        },
    };
}
