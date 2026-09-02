/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const PORTALED_MODAL_COMPONENT_NAMES = [
    'sylius_paypal:create_sandbox_modal',
    'sylius_paypal:create_onboarding_modal',
];

function getPortalWrapper(modal) {
    const parent = modal.parentElement;

    if (
        parent === null ||
        parent === document.body ||
        !PORTALED_MODAL_COMPONENT_NAMES.includes(parent.getAttribute('data-live-name-value'))
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
