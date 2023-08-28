export default function PasswordSwitcher() {
  const passwordFields = document.querySelectorAll('[type="password"]');

  [...passwordFields].forEach((el) => {
    const icon = el.nextElementSibling;
    if (!icon?.matches('.Input-switchPassword')) return null;

    icon.addEventListener('click', (e) => {
      e.preventDefault();

      icon.classList.toggle('is-noVisible');
      el.setAttribute(
        'type',
        el.getAttribute('type') === 'password' ? 'text' : 'password'
      );
    });
    return null;
  });
}
