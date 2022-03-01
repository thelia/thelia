export const Modal = () => {
    let updateButton = document.getElementById('updateDetails');
    let favDialog = document.getElementById('favDialog');
    let selectEl = document.querySelector('select');
    let confirmBtn = document.getElementById('confirmBtn');
    
    updateButton.addEventListener('click', function onOpen() {
      if (typeof favDialog.showModal === "function") {
        favDialog.showModal();
      } 
    });
    selectEl.addEventListener('change', function onSelect(e) {
      confirmBtn.value = selectEl.value;
    });
}

