import '@splidejs/splide/dist/css/splide-core.min.css';
import '@splidejs/splide/dist/css/themes/splide-default.min.css';

import Splide from '@splidejs/splide';

export default function ProductGallery() {
  new Splide('.splide', {
    pagination: false,
    arrows: false,
    padding: '2rem',
    gap: '1rem',
    breakpoints: {
      5000: {
        destroy: true
      },
      1024: {
        type: 'loop'
      }
    }
  }).mount();
}
