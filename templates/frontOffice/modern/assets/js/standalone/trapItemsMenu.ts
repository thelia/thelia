export const BREAKPOINT_MOBILE = 768;

export default function trapItemsMenu() {
  const categories = document.querySelectorAll('.js-toggleCategory');

  if (!categories.length) return;

  categories.forEach((category) => {
    category.addEventListener('click', (e) => {
      const oldActivated = document.querySelector('li.active');
      if (oldActivated && oldActivated !== category.parentElement) {
        oldActivated.classList.remove('active');
      }
      category.parentElement?.classList.toggle('active');
    });

    const sub = category.parentElement?.querySelector('.Menu-sub1');

    sub?.addEventListener('keydown', (e) => {
      trapTabKey(sub as HTMLElement, e as KeyboardEvent);
      trapEscape(category as HTMLElement, e as KeyboardEvent);
    });
  });
}
export function trapTabKey(container: HTMLElement, event: KeyboardEvent) {
  const { shiftKey, keyCode } = event;

  if (keyCode !== 9) return;
  // get list of focusable items
  const focusableItems = [
    ...container.querySelectorAll('a[href],input,button:not(.no-focusTrap)')
  ];

  // get currently focused item
  const focusedItem = document.querySelector(':focus');
  // get the number of focusable items
  const numberOfFocusableItems = focusableItems.length;

  // get the index of the currently focused item
  const focusedItemIndex = focusableItems.indexOf(focusedItem as HTMLElement);

  if (shiftKey) {
    //back tab
    // if focused on first item and user preses back-tab, go to the last focusable item
    if (focusedItemIndex !== 0) return;

    (focusableItems[numberOfFocusableItems - 1] as HTMLElement)?.focus();
    event.preventDefault();
  } else {
    //forward tab
    // if focused on the last item and user preses tab, go to the first focusable item
    if (focusedItemIndex !== numberOfFocusableItems - 1) return;

    (focusableItems[0] as HTMLElement)?.focus();
    event.preventDefault();
  }
}

export function trapEscape(container: HTMLElement, event: KeyboardEvent) {
  event.stopPropagation();

  if (event.keyCode !== 27) return;

  container.parentElement?.classList.remove('active');
  container.focus();
}
