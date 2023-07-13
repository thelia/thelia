import MmenuLight from 'mmenu-light';
import observeStickyElement from '@utils/manageStickyElement';
import trapItemsMenu, { BREAKPOINT_MOBILE } from '@js/standalone/trapItemsMenu';

export default function Listener() {
  const target = document?.querySelector('#main-navigation');
  const burger = document.querySelector('[data-toggle-navigation]');

  if (!target) return null;

  const menu = new MmenuLight(target, `(max-width: ${BREAKPOINT_MOBILE}px)`);

  menu.navigation();
  const drawer = menu.offcanvas();

  burger.addEventListener('click', (e) => {
    e.preventDefault();

    document.body.classList.toggle('is-open');

    if (document.body.classList.contains('is-open')) {
      drawer.open();
    } else {
      drawer.close();
    }
  });

  isSticky();

  trapItemsMenu();
}

function isSticky() {
  const nav = document.getElementById('StickyToggler');

  if (!nav) return;

  observeStickyElement(nav, 'is-sticky');
}
