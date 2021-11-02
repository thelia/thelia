import MmenuLight from 'mmenu-light';

export default function Listener() {
  const target = document.querySelector('#MainNavigation');
  if(!target) return null;
  
  const menu = new MmenuLight(target);
  
  menu.navigation();
  const drawer = menu.offcanvas();

  document
    .querySelector('[data-toggle-navigation]')
    .addEventListener('click', (evnt) => {
      evnt.preventDefault();
      drawer.open();
    });
}
