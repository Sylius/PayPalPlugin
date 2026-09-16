import { loadWebSdkOnce } from './paypal-web-sdk';

let session = null;

export function paymentPageSession(config) {
    session ??= createSession(config);

    return session;
}

async function createSession({ scriptUrl, instanceConfig, currencyCode, createOrderUrl }) {
    await loadWebSdkOnce(scriptUrl);

    const sdkInstance = await window.paypal.createInstance(instanceConfig);
    const eligibleMethods = await sdkInstance.findEligibleMethods({ currencyCode });

    let busy = false;
    let orderId = null;

    return {
        sdkInstance,

        isEligible: (fundingSource) => eligibleMethods.isEligible(fundingSource),

        isBusy: () => busy,

        currentOrderId: () => orderId,

        release: () => {
            busy = false;
        },

        startAttempt: async () => {
            busy = true;
            orderId = null;

            const response = await fetch(createOrderUrl, { method: 'post' });
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
