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

    const loadedScript = document.querySelector('script[data-paypal-partner-js]');

    if (loadedScript !== null && loadedScript.getAttribute('src') !== partnerJsUrl) {
        loadedScript.remove();
        delete window.PAYPAL;
    } else if (loadedScript !== null) {
        const signup = window.PAYPAL && window.PAYPAL.apps && window.PAYPAL.apps.Signup;

        if (signup && typeof signup.setup === 'function') {
            // Partners ramped to the zoid mini-browser (the current default) expose `setup()`,
            // which re-queries `[data-paypal-button]` and re-binds the click handler.
            signup.setup();
        } else if (signup && typeof signup.render === 'function') {
            // Partners still on the legacy lightbox/signup.js flow expose `render()` for the same purpose.
            signup.render();
        }

        return;
    }

    const script = document.createElement('script');
    script.id = 'paypal-js';
    script.src = partnerJsUrl;
    script.dataset.paypalPartnerJs = 'true';
    document.body.appendChild(script);
}

let activeOnboardingModal = null;

function findOnboardingModals() {
    return document.querySelectorAll('[data-paypal-onboarding-complete-url]');
}

function findOnboardingModal() {
    return activeOnboardingModal ?? document.querySelector('[data-paypal-onboarding-complete-url]');
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

function loadPartnerJsIfButtonExists(onboardingModal) {
    if (activeOnboardingModal === onboardingModal && onboardingModal.querySelector('[data-paypal-button]') !== null) {
        loadPartnerJs(onboardingModal.getAttribute('data-paypal-onboarding-partner-js-url'));
    }
}

function loadPartnerJsOnceButtonExists(onboardingModal) {
    const observer = new MutationObserver(() => {
        loadPartnerJsIfButtonExists(onboardingModal);
    });
    observer.observe(onboardingModal, { childList: true, subtree: true });
}

function bindOnboardingUrlLoader(onboardingModal) {
    document.querySelectorAll(`[data-bs-target="#${onboardingModal.id}"]`).forEach((trigger) => {
        trigger.addEventListener('click', () => {
            activeOnboardingModal = onboardingModal;
            onboardingModal.dispatchEvent(new Event('paypal:onboarding-open'));
        });
    });
}

function initOnboardingModal() {
    findOnboardingModals().forEach((onboardingModal) => {
        bindOnboardingUrlLoader(onboardingModal);
        loadPartnerJsOnceButtonExists(onboardingModal);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOnboardingModal);
} else {
    initOnboardingModal();
}
