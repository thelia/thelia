import { trapTabKey } from '@js/standalone/trapItemsMenu';

function manageFocusOnMyAccount() {
  const account = document.querySelector('#Header-Account');

  if (!account) return;

  account.addEventListener('keydown', (e) => {
    const { key } = e;

    if (!['Enter', 'Escape'].includes(key)) return;

    e.preventDefault();
    e.stopPropagation();

    const menu = account.querySelector('.Header-dropdown');

    if (!menu) return;

    menu.classList.toggle('open');

    const firstItem = menu.querySelector('a');

    if (!firstItem) return;

    firstItem.focus();

    menu.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        account.querySelector('a').focus();
        return;
      }

      trapTabKey(menu, event);
    });

    menu.addEventListener('focusout', (e) => {
      menu.classList.remove('open');
    });
  });
}

function Header() {
  manageFocusOnMyAccount();
}

export default Header;
