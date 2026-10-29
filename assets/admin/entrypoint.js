import './scripts/live-component-modal-portal';
import './scripts/paypal-onboarding';
import './scripts/paypal-mode-switch';

const imagesContext = require.context('./images', true, /\.svg$/);
imagesContext.keys().forEach(imagesContext);
