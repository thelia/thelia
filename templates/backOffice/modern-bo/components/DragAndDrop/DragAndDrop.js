export const DragAndDrop = () => {
    
    const fileUpload = document.getElementById('file_upload');
    for (let i = 0; i < fileUpload.length; i++){
        fileUpload[i].addEventListener("click", e => {
            uploadFiles(e);
        })
    }


    function uploadFiles() {
        const files = document.getElementById('file_upload').files;

        if(files.length === 0){
          alert("Attention, vous devez choisir un fichier");
          return;
        }

        let filenames="";
        for(let i = 0; i < files.length; i++){
          filenames+=files[i].name+"\n";
        }
        alert("Vous avez sélectionné:"+filenames);
      }
}
  