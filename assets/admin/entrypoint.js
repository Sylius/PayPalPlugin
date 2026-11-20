import './scripts/live-component-modal-portal';
import './scripts/paypal-onboarding';
import './styles/paypal-onboarding.scss';

const imagesContext = require.context('./images', true, /\.svg$/);
imagesContext.keys().forEach(imagesContext);
