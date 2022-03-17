export const DragAndDrop = () => {
  var dropzone = document.getElementById('dropzone');
  var dropzone_input = dropzone.querySelector('.dropzone-input');
  var multiple = dropzone_input.getAttribute('multiple') ? true : false;
  
  ['drag', 'dragstart', 'dragend', 'dragover', 'dragenter', 'dragleave', 'drop'].forEach(function(event) {
    dropzone.addEventListener(event, function(e) {
      e.preventDefault();
      e.stopPropagation();
    });
  });
  
  dropzone.addEventListener('dragover', function(e) {
    this.classList.add('dropzone-dragging');
  }, false);
  
  dropzone.addEventListener('dragleave', function(e) {
    this.classList.remove('dropzone-dragging');
  }, false);
  
  dropzone.addEventListener('drop', function(e) {
    this.classList.remove('dropzone-dragging');
    var files = e.dataTransfer.files;
    var dataTransfer = new DataTransfer();
    
    var for_alert = "";
    Array.prototype.forEach.call(files, file => {
      for_alert += "# " + file.name +
      " (" + file.type + " | " + file.size +
      " bytes)\r\n";
      dataTransfer.items.add(file);
      if (!multiple) {
        return false;
      }
    });
  
    var filesToBeAdded = dataTransfer.files;
    dropzone_input.files = filesToBeAdded;
    alert(for_alert);
    
  }, false);
  
  dropzone.addEventListener('click', function(e) {
    dropzone_input.click();
  });
  
  
  
  
}
  