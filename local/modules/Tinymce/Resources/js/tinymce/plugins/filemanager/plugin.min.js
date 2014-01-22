/**
 * plugin.js
 *
 * Copyright, Alberto Peripolli
 * Released under Creative Commons Attribution-NonCommercial 3.0 Unported License.
 *
 * Contributing: https://github.com/trippo/ResponsiveFilemanager
 */
tinymce.PluginManager.add("filemanager",function(e){function t(t,n,r,i){urltype=2;if(r=="image"){urltype=1}if(r=="media"){urltype=3}var s="RESPONSIVE FileManager";if(typeof tinymce.settings.filemanager_title!=="undefined"&&tinymce.settings.filemanager_title)s=tinymce.settings.filemanager_title;var o="";var u="false";if(typeof tinymce.settings.filemanager_sort_by!=="undefined"&&tinymce.settings.filemanager_sort_by)o=tinymce.settings.filemanager_sort_by;if(typeof tinymce.settings.filemanager_descending!=="undefined"&&tinymce.settings.filemanager_descending)u=tinymce.settings.filemanager_descending;tinymce.activeEditor.windowManager.open({title:s,file:tinymce.settings.external_filemanager_path+"dialog.php?type="+urltype+"&descending="+u+"&sort_by="+o+"&lang="+tinymce.settings.language,width:880,height:570,resizable:true,maximizable:true,inline:1},{setUrl:function(n){var r=i.document.getElementById(t);r.value=e.convertURL(n);if("fireEvent"in r){r.fireEvent("onchange")}else{var s=document.createEvent("HTMLEvents");s.initEvent("change",false,true);r.dispatchEvent(s)}}})}tinymce.activeEditor.settings.file_browser_callback=t;return false})