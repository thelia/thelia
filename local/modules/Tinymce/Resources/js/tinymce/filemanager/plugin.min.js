/**
 * plugin.js
 *
 * Copyright, Alberto Peripolli
 * Released under Creative Commons Attribution-NonCommercial 3.0 Unported License.
 *
 * Contributing: https://github.com/trippo/ResponsiveFilemanager
 */
tinymce.PluginManager.add("filemanager",function(e){function t(t,n,r,i){urltype=2;if(r=="image"){urltype=1}if(r=="media"){urltype=3}var s="RESPONSIVE FileManager";if(typeof e.settings.filemanager_title!=="undefined"&&e.settings.filemanager_title){s=e.settings.filemanager_title}var o="key";if(typeof e.settings.filemanager_access_key!=="undefined"&&e.settings.filemanager_access_key){o=e.settings.filemanager_access_key}var u="";if(typeof e.settings.filemanager_sort_by!=="undefined"&&e.settings.filemanager_sort_by){u="&sort_by="+e.settings.filemanager_sort_by}var a="false";if(typeof e.settings.filemanager_descending!=="undefined"&&e.settings.filemanager_descending){a=e.settings.filemanager_descending}var f="";if(typeof e.settings.filemanager_subfolder!=="undefined"&&e.settings.filemanager_subfolder){f="&fldr="+e.settings.filemanager_subfolder}tinymce.activeEditor.windowManager.open({title:s,file:e.settings.external_filemanager_path+"dialog.php?type="+urltype+"&descending="+a+u+f+"&lang="+e.settings.language+"&akey="+o,width:860,height:570,resizable:true,maximizable:true,inline:1},{setUrl:function(n){var r=i.document.getElementById(t);r.value=e.convertURL(n);if("fireEvent"in r){r.fireEvent("onchange")}else{var s=document.createEvent("HTMLEvents");s.initEvent("change",false,true);r.dispatchEvent(s)}}})}tinymce.activeEditor.settings.file_browser_callback=t;return false})