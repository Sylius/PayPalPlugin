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

    return {
        sdkInstance,

        isEligible: (fundingSource) => eligibleMethods.isEligible(fundingSource),

        isBusy: () => busy,

        release: () => {
            busy = false;
        },

        startAttempt: async () => {
            busy = true;

            const response = await fetch(createOrderUrl, { method: 'post' });
            if (!response.ok) {
                busy = false;

                throw new Error(`Could not start a PayPal payment attempt (${response.status}).`);
            }

            const data = await response.json();

            return { orderId: data.orderId };
        },
    };
}
