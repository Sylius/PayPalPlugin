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

    // stopImmediatePropagation() above prevents this event from ever reaching listeners on the modal
    // element itself (e.g. a component's own data-action="show.bs.modal->live#action"), since it was
    // stopped during the capture phase before it got there. Re-dispatch it now that the element has
    // settled in its new (portaled) position, so such listeners still see the modal opening.
    event.target.dispatchEvent(new Event(event.type, { bubbles: true }));
}

document.addEventListener('show.bs.modal', portalLiveComponentModal, { capture: true });
