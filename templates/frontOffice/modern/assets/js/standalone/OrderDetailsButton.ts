export default function OrderDetailsButton() {
  const orderDetails = document.querySelectorAll('.Order details');

  if (!orderDetails) return null;

  orderDetails.forEach((el) => {
    el.addEventListener('click', (e) => {
      const arrow = el.querySelector('summary > div');

      if (el.hasAttribute('open')) {
        arrow?.classList.remove('rotate-90');
        arrow?.classList.add('-rotate-90');
      } else {
        arrow?.classList.remove('-rotate-90');
        arrow?.classList.add('rotate-90');
      }
    });
  });
  return null;
}
