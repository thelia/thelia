export const DragAndDropMedia = () => {
  (function() {
    function Init() {
      let fileSelect = document.getElementById('file-upload-three');
      let fileDrag = document.getElementById('file-drag-three');
  
      fileSelect.addEventListener('change', false);
  
      let http = new XMLHttpRequest();
      if (http.upload) 
      {
        fileDrag.addEventListener('dragover', fileDragHover, false);
        fileDrag.addEventListener('dragleave', fileDragHover, false);
        fileDrag.addEventListener('drop', false);
      }
    }
  
    function fileDragHover(e) {
      var fileDrag = document.getElementById('file-drag-three');
  
      e.stopPropagation();
      e.preventDefault();
      
      fileDrag.className = (e.type === 'dragover' ? 'hover' : 'modal-body file-upload-three');
    }
  
    if (window.File && window.FileList && window.FileReader) {
      Init();
    } else {
      document.getElementById('file-drag-three').style.display = 'none';
    }
  })();
}
  