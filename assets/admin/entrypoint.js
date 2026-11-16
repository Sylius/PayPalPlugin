import './scripts/live-component-modal-portal';
import './scripts/paypal-onboarding';

const imagesContext = require.context('./images', true, /\.svg$/);
imagesContext.keys().forEach(imagesContext);
