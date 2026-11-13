/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

let partnerJsReady = false;

function syncOnboardingButtonsState() {
    document.querySelectorAll('[data-paypal-button]').forEach((button) => {
        button.classList.toggle('disabled', !partnerJsReady);
        button.setAttribute('aria-disabled', partnerJsReady ? 'false' : 'true');
        button.setAttribute('aria-busy', partnerJsReady ? 'false' : 'true');
        button.querySelector('[data-paypal-button-loading]')?.classList.toggle('d-none', partnerJsReady);
    });
}

function showOnboardingError() {
    const errorBox = findOnboardingModal()?.querySelector('[data-paypal-onboarding-error]');

    if (errorBox !== null && errorBox !== undefined) {
        errorBox.classList.remove('d-none');
    }
}

function loadPartnerJs(partnerJsUrl) {
    if (!partnerJsUrl) {
        return;
    }

    const loadedScript = document.querySelector('script[data-paypal-partner-js]');

    if (null !== loadedScript) {
        if (!partnerJsReady) {
            return;
        }

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

    partnerJsReady = false;
    syncOnboardingButtonsState();

    const script = document.createElement('script');
    script.id = 'paypal-js';
    script.src = partnerJsUrl;
    script.dataset.paypalPartnerJs = 'true';
    script.addEventListener('load', () => {
        partnerJsReady = true;
        syncOnboardingButtonsState();
    });
    script.addEventListener('error', () => {
        document.querySelectorAll('[data-paypal-button-loading]').forEach((spinner) => spinner.classList.add('d-none'));
        showOnboardingError();
    });
    document.body.appendChild(script);
}

function guardOnboardingButtonClick(event) {
    if (!(event.target instanceof Element) || event.target.closest('[data-paypal-button]') === null) {
        return;
    }

    if (partnerJsReady) {
        wrapMiniBrowserParentWindowUpdate();

        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
}

let activeOnboardingModal = null;

function findOnboardingModals() {
    return document.querySelectorAll('[data-paypal-onboarding-complete-url]');
}

function findOnboardingModal() {
    return activeOnboardingModal ?? document.querySelector('[data-paypal-onboarding-complete-url]');
}

const MINI_BROWSER_CLOSE_TIMEOUT = 1000;

let onboardingRedirectUrl = null;
let miniBrowserUpdatedParentWindow = false;

function wrapMiniBrowserParentWindowUpdate() {
    const miniBrowser = window.PAYPAL && window.PAYPAL.apps && window.PAYPAL.apps.Signup && window.PAYPAL.apps.Signup.MiniBrowser;

    if (!miniBrowser || typeof miniBrowser.updateParentWindow !== 'function' || true === miniBrowser.updateParentWindow.syliusWrapped) {
        return;
    }

    const updateParentWindow = miniBrowser.updateParentWindow;
    const wrappedUpdateParentWindow = function (url, ...args) {
        miniBrowserUpdatedParentWindow = true;

        return updateParentWindow.call(this, onboardingRedirectUrl ?? url, ...args);
    };
    wrappedUpdateParentWindow.syliusWrapped = true;

    miniBrowser.updateParentWindow = wrappedUpdateParentWindow;
}

function closeMiniBrowsers() {
    const component = window.PAYPAL?.apps?.Signup?.MiniBrowser?.component;
    const instances = Array.isArray(component?.instances) ? [...component.instances] : [];
    const closing = instances.map((instance) => Promise.resolve().then(() => instance.close()));

    return Promise.race([
        Promise.allSettled(closing),
        new Promise((resolve) => {
            setTimeout(resolve, MINI_BROWSER_CLOSE_TIMEOUT);
        }),
    ]);
}

function showOnboardingSuccess(modal, redirectUrl) {
    const successBox = modal.querySelector('[data-paypal-onboarding-success]');

    if (successBox === null) {
        return;
    }

    successBox.querySelector('[data-paypal-onboarding-success-link]')?.setAttribute('href', redirectUrl);
    successBox.classList.remove('d-none');
}

function finishOnboarding(redirectUrl) {
    onboardingRedirectUrl = redirectUrl;

    closeMiniBrowsers().then(() => {
        if (!miniBrowserUpdatedParentWindow) {
            window.location.href = redirectUrl;
        }
    });
}

window.syliusPayPalOnboardedCallback = function onboardedCallback(authCode, sharedId) {
    const modal = findOnboardingModal();

    if (modal === null) {
        return;
    }

    const completeUrl = modal.getAttribute('data-paypal-onboarding-complete-url');
    const sandbox = modal.getAttribute('data-paypal-onboarding-sandbox') === 'true';
    const errorBox = modal.querySelector('[data-paypal-onboarding-error]');

    fetch(completeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ authCode, sharedId, sandbox }),
        keepalive: true,
    })
        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            if (data && data.redirectUrl) {
                if (ok && data.upToDate !== false) {
                    showOnboardingSuccess(modal, data.redirectUrl);
                }

                finishOnboarding(data.redirectUrl);

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
        syncOnboardingButtonsState();
    }
}

function loadPartnerJsOnceButtonExists(onboardingModal) {
    const observer = new MutationObserver(() => {
        loadPartnerJsIfButtonExists(onboardingModal);
    });
    observer.observe(onboardingModal, { childList: true, subtree: true });
}

// Opening the modal moves its live component to the body, which reconnects the component. An action sent before the
// component is connected again is dropped, so the URL is requested on the next task after the modal is shown.
function loadOnboardingUrlOnceShown(event) {
    const onboardingModal = event.target;

    if (!(onboardingModal instanceof Element) || !onboardingModal.hasAttribute('data-paypal-onboarding-complete-url')) {
        return;
    }

    activeOnboardingModal = onboardingModal;
    setTimeout(() => {
        onboardingModal.dispatchEvent(new Event('paypal:onboarding-open'));
    });
}

function initOnboardingModal() {
    document.addEventListener('click', guardOnboardingButtonClick, true);
    document.addEventListener('shown.bs.modal', loadOnboardingUrlOnceShown);

    findOnboardingModals().forEach((onboardingModal) => {
        loadPartnerJsOnceButtonExists(onboardingModal);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOnboardingModal);
} else {
    initOnboardingModal();
}
