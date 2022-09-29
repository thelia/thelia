export default function Modal() {
  let openmodal = document.querySelectorAll('[data-target-modal]');

  for (let i = 0; i < openmodal.length; i++) {
    const target = document.querySelector(openmodal[i].dataset.targetModal);
    if (!target) continue;

    openmodal[i].addEventListener('click', function (event) {
      event.preventDefault();
      toggleModal(target);
    });
  }

  const overlay = document.querySelector('.Modal-overlay');
  if (overlay) {
    overlay.addEventListener('click', () => toggleModal());
  }

  let closemodal = document.querySelectorAll('.Modal-close');
  for (let i = 0; i < closemodal.length; i++) {
    closemodal[i].addEventListener('click', () => toggleModal());
  }

  document.onkeydown = function (evt) {
    evt = evt || window.event;
    let isEscape = false;
    if ('key' in evt) {
      isEscape = evt.key === 'Escape' || evt.key === 'Esc';
    } else {
      isEscape = evt.keyCode === 27;
    }
    if (isEscape && document.body.classList.contains('Modal-active')) {
      toggleModal();
    }
  };

  function toggleModal(target) {
    const body = document.querySelector('body');
    const modals = [...document.querySelectorAll('.Modal')];

    if (target) {
      target.classList.remove('opacity-0');
      target.classList.remove('pointer-events-none');
      body.classList.add('Modal-active');
    } else {
      for (const modal of modals) {
        modal.classList.add('opacity-0');
        modal.classList.add('pointer-events-none');
        body.classList.remove('Modal-active');
      }
    }
  }
}
