import MmenuLight from 'mmenu-light';

export default function Listener() {
  const menu = new MmenuLight(document.querySelector('#MainNavigation'));

  menu.navigation();
  const drawer = menu.offcanvas();

  document
    .querySelector('[data-toggle-navigation]')
    .addEventListener('click', (evnt) => {
      evnt.preventDefault();
      drawer.open();
    });
}
