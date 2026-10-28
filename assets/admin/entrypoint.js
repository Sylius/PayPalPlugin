import './scripts/live-component-modal-portal';

const imagesContext = require.context('./images', true, /\.svg$/);
imagesContext.keys().forEach(imagesContext);
