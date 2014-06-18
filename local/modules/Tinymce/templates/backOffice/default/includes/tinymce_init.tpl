<script src="{url file='/tinymce/tinymce.min.js'}"></script>

<script>
    tinymce.init({
        selector: ".wysiwyg",
        theme: "modern",

        // height of the editor zone
        //height: 500,

        // Set it to true to display the menubar.
        menubar : false,

        // Available language are in Resources/js/tinymce/langs
        //language: "{lang attr='locale'}",

        // Available language are in Resources/js/tinymce/plugins
        plugins: [
            "advlist autolink link image lists charmap print preview hr anchor pagebreak",
            "searchreplace wordcount visualblocks visualchars insertdatetime media nonbreaking",
            "table contextmenu directionality emoticons paste textcolor responsivefilemanager",
            "youtube"
        ],

        toolbar1: "undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | styleselect | filemanager | link unlink anchor | image media | youtube | forecolor backcolor  | print preview code ",
        image_advtab: true ,
        external_filemanager_path:"{url file='/tinymce/filemanager/'}",
        filemanager_title:"{intl l='Files manager'}" ,
        external_plugins: { "filemanager" : "{url file='/tinymce/filemanager/plugin.min.js'}"},

        // Always paste as text, removing external formatting
        //paste_as_text: true,

        // All newlines are <p>, Shift+enter inserts <br />
        //force_p_newlines : true,

        relative_urls : false,
        document_base_url : "{url path="/media"}",

        content_css: "{stylesheets file='assets/css/editor.less' filters='less' source='Tinymce'}{$asset_url}{/stylesheets}"
    });
</script>
