<script src="{url file='/tinymce/tinymce.min.js'}"></script>

<script>
    tinymce.init({
        selector: ".wysiwyg",
        theme: "modern",

        // height of the editor zone
        //height: 500,

        // Set it to true to display the menubar.
        menubar : false,

        // Use our smarty plugin to guess the best available language
        language: "{tinymce_lang}",

        // See available plugins at http://www.tinymce.com/wiki.php/Plugins
        plugins: [
            "advlist autolink link image lists charmap print preview hr anchor pagebreak",
            "searchreplace wordcount visualblocks visualchars insertdatetime media nonbreaking",
            "table contextmenu directionality emoticons paste textcolor responsivefilemanager",
            "fullscreen code youtube importcss"
        ],

        // See available controls at http://www.tinymce.com/wiki.php/Controls
        toolbar1: "undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | hr | styleselect | filemanager | code | fullscreen",
        toolbar2: "link unlink anchor | image responsivefilemanager media youtube | forecolor backcolor | charmap | print preview ",

        image_advtab: true,

        // File manager configuration
        external_filemanager_path: "{url file='/tinymce/filemanager/'}",
        filemanager_title: "{intl l='File manager' d='tinymce.bo.default'}" ,
        external_plugins: { "filemanager" : "{url file='/tinymce/filemanager/plugin.min.js'}"},

        // Always paste as text, removing external formatting when pasting text
        //paste_as_text: true,

        // All newlines are <p>, Shift+enter inserts <br />
        //force_p_newlines : true,

        relative_urls : false,
        document_base_url : "{url path="/"}",

        // Styles (CSS or LESS) available in the editor could be defined in assets/css/editor.less file.
        {stylesheets file='assets/css/editor.less' filters='less' source='Tinymce'}
        content_css: "{$asset_url}",
        importcss_append: true
        {/stylesheets}
    });
</script>
