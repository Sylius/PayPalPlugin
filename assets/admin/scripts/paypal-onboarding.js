/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function loadPartnerJs(partnerJsUrl) {
    if (!partnerJsUrl) {
        return;
    }

    if (document.querySelector('script[data-paypal-partner-js]') !== null) {
        if (window.PAYPAL && window.PAYPAL.apps && window.PAYPAL.apps.Signup
            && typeof window.PAYPAL.apps.Signup.render === 'function') {
            window.PAYPAL.apps.Signup.render();
        }

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

function loadPartnerJsOnceButtonExists(onboardingModal) {
    if (onboardingModal.querySelector('[data-paypal-button]') !== null) {
        loadPartnerJs(onboardingModal.getAttribute('data-paypal-onboarding-partner-js-url'));

        return;
    }

    const observer = new MutationObserver(() => {
        if (onboardingModal.querySelector('[data-paypal-button]') !== null) {
            loadPartnerJs(onboardingModal.getAttribute('data-paypal-onboarding-partner-js-url'));
        }
    });
    observer.observe(onboardingModal, { childList: true, subtree: true });
}

function bindOnboardingUrlLoader(onboardingModal) {
    document.querySelectorAll(`[data-bs-target="#${onboardingModal.id}"]`).forEach((trigger) => {
        trigger.addEventListener('click', () => {
            onboardingModal.dispatchEvent(new Event('paypal:onboarding-open'));
        });
    });
}

function initOnboardingModal() {
    const onboardingModal = findOnboardingModal();
    if (onboardingModal !== null) {
        bindOnboardingUrlLoader(onboardingModal);
        loadPartnerJsOnceButtonExists(onboardingModal);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOnboardingModal);
} else {
    initOnboardingModal();
}
