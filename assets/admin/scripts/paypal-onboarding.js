/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function loadPartnerJs(partnerJsUrl) {
    if (!partnerJsUrl || document.querySelector('script[data-paypal-partner-js]') !== null) {
        return;
    }

    const script = document.createElement('script');
    script.id = 'paypal-js';
    script.src = partnerJsUrl;
    script.dataset.paypalPartnerJs = 'true';
    document.body.appendChild(script);
}

function findOnboardingModal() {
    return document.querySelector('[data-paypal-onboarding-complete-url]');
}

window.syliusPayPalOnboardedCallback = function onboardedCallback(authCode, sharedId) {
    const modal = findOnboardingModal();

    if (modal === null) {
        return;
    }

    const completeUrl = modal.getAttribute('data-paypal-onboarding-complete-url');
    const errorBox = modal.querySelector('[data-paypal-onboarding-error]');

    fetch(completeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ authCode, sharedId }),
    })
        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
        .then(({ data }) => {
            if (data && data.redirectUrl) {
                window.location.href = data.redirectUrl;

                return;
            }

            if (errorBox !== null) {
                errorBox.classList.remove('d-none');
            }
        })
        .catch(() => {
            if (errorBox !== null) {
                errorBox.classList.remove('d-none');
            }
        });
};

// partner.js is loaded as soon as the onboarding modal markup is present on the page (matching PayPal's own
// integration snippet, which embeds the script unconditionally rather than lazily on modal open) — this avoids
// depending on the "show.bs.modal" event, which live-component-modal-portal.js can stop from propagating further.
function loadPartnerJsIfOnboardingModalPresent() {
    const onboardingModal = findOnboardingModal();
    if (onboardingModal !== null) {
        loadPartnerJs(onboardingModal.getAttribute('data-paypal-onboarding-partner-js-url'));
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadPartnerJsIfOnboardingModalPresent);
} else {
    loadPartnerJsIfOnboardingModalPresent();
}
