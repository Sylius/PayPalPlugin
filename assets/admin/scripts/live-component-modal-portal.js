/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const MODAL_COMPONENT_NAMES = [
    'sylius_paypal:sandbox_onboarding_modal',
    'sylius_paypal:live_onboarding_modal',
];

function getPortalWrapper(modal) {
    const parent = modal.parentElement;

    if (
        parent === null ||
        parent === document.body ||
        !MODAL_COMPONENT_NAMES.includes(parent.getAttribute('data-live-name-value'))
    ) {
        return null;
    }

    return parent;
}

function portalLiveComponentModal(event) {
    const wrapper = getPortalWrapper(event.target);

    if (wrapper === null) {
        return;
    }

    const componentName = wrapper.getAttribute('data-live-name-value');
    const placeholder = document.createComment(componentName);
    wrapper.before(placeholder);
    document.body.appendChild(wrapper);

    event.target.addEventListener('hidden.bs.modal', () => placeholder.replaceWith(wrapper), { once: true });
    event.stopImmediatePropagation();
}

document.addEventListener('show.bs.modal', portalLiveComponentModal, { capture: true });
