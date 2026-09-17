import { loadWebSdkOnce } from './paypal-web-sdk';

let session = null;

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

    let busy = false;
    let orderId = null;

    return {
        sdkInstance,

        isEligible: (fundingSource) => eligibleMethods.isEligible(fundingSource),

        getDetails: (fundingSource) => eligibleMethods.getDetails(fundingSource),

        isBusy: () => busy,

        currentOrderId: () => orderId,

        release: () => {
            busy = false;
        },

        startAttempt: async (paymentSource = null) => {
            busy = true;
            orderId = null;

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
            orderId = data.orderId;

            return { orderId };
        },
    };
}
