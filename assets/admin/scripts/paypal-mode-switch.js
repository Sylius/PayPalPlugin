/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

function selectedMode(select) {
    return select.value === 'sandbox' ? 'sandbox' : 'production';
}

function isConfigured(root, mode) {
    return root.getAttribute('data-' + mode + '-configured') === '1';
}

function previewCredentials(root, credentials, mode) {
    const form = root.closest('form');
    if (form === null) {
        return;
    }

    const modeCredentials = credentials[mode] || {};
    const clientId = form.querySelector('[name$="[client_id]"]');
    const clientSecret = form.querySelector('[name$="[client_secret]"]');

    if (clientId !== null) {
        clientId.value = modeCredentials.client_id || '';
    }
    if (clientSecret !== null) {
        clientSecret.value = modeCredentials.client_secret || '';
    }
}

function updateSetupButtons(root, mode) {
    root.querySelectorAll('[data-paypal-setup-mode]').forEach((button) => {
        button.classList.toggle('d-none', button.getAttribute('data-paypal-setup-mode') !== mode);
    });
}

function updateSandboxWarning(root, mode) {
    const warning = root.querySelector('[data-paypal-sandbox-warning]');
    if (warning !== null) {
        warning.classList.toggle('d-none', mode !== 'sandbox');
    }
}

function updateCredentialFields(root, mode) {
    const scope = root.closest('form') || document;
    const hidden = !isConfigured(root, mode);

    scope.querySelectorAll('[data-paypal-credential-field]').forEach((field) => {
        field.classList.toggle('d-none', hidden);
    });
}

function initModeSwitch() {
    const root = document.querySelector('[data-paypal-mode-switch]');
    if (root === null) {
        return;
    }

    const select = root.querySelector('select');
    if (select === null) {
        return;
    }

    let credentials = {};
    try {
        credentials = JSON.parse(root.getAttribute('data-paypal-credentials')) || {};
    } catch (error) {
        credentials = {};
    }

    updateSetupButtons(root, selectedMode(select));
    updateSandboxWarning(root, selectedMode(select));
    updateCredentialFields(root, selectedMode(select));

    select.addEventListener('change', () => {
        const mode = selectedMode(select);

        updateSetupButtons(root, mode);
        updateCredentialFields(root, mode);
        updateSandboxWarning(root, mode);

        if (isConfigured(root, mode)) {
            previewCredentials(root, credentials, mode);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModeSwitch);
} else {
    initModeSwitch();
}
