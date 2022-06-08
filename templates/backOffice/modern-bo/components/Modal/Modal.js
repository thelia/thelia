export const Modal = () => {
  document.addEventListener('DOMContentLoaded', function() {
    const modal = document.querySelector('.modal');
    const openModal = document.querySelector('.open-button');
    const closeModal = document.querySelector('.close-button');

    if (openModal) {
      openModal.addEventListener('click', () => {
        modal.showModal();
      });
    }



    closeModal.addEventListener('click', () => {
      modal.close();
    });
  });
}
