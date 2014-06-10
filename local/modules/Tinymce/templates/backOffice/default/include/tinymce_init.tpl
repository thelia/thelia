<script src="{url file='/tinymce/tinymce.min.js'}"></script>

<script>
    tinymce.init({
        selector: ".wysiwyg",
        theme: "modern",
        menubar : false,
        language: "",
        plugins: [
            "advlist autolink link image lists charmap print preview hr anchor pagebreak",
            "searchreplace wordcount visualblocks visualchars insertdatetime media nonbreaking",
            "table contextmenu directionality emoticons paste textcolor filemanager code"
        ],
        toolbar1: "undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | styleselect | filemanager | link unlink anchor | image media | forecolor backcolor  | print preview code ",
        image_advtab: true ,
        external_filemanager_path:"{url file='/tinymce/plugins/filemanager/'}",
        filemanager_title:"{intl l='Files manager'}" ,
        external_plugins: { "filemanager" : "{url file='/tinymce/plugins/filemanager/plugin.min.js'}"}
    });
</script>