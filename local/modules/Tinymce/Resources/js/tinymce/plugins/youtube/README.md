Plugin youtube for TinyMCE 4
======================

Insert YouTube video W3C valid with optionnals (HD, similar vidéos)


Authors
-------

 * Gerits Aurelien (Author-Developer) contact[at]aurelien-gerits[point]be

Official link in french :

###Screenshot

![tinyMCE plugin YouTube](http://blog.aurelien-gerits.be/wp-content/uploads/2013/09/youtube-tinymce-2.0.png "tinyMCE plugin YouTube")

###Installation
 * Download the dist/youtube.zip archive
 * Unzip archive in tinyMCE plugin directory (tiny_mce/plugins/)

###Configuration
 ```html
<script type="text/javascript">
tinymce.init({
	selector: "textarea",
	plugins: [
			"advlist autolink lists link image charmap print preview anchor",
			"searchreplace visualblocks code fullscreen",
			"insertdatetime media table contextmenu paste youtube"
			],
	toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image| youtube"
	});
</script>
```

###Languages
 * English
 * French
 * Russian
 * Spanish
 * German
 * Italian
 * Brazilian
 * Hungarian
 * Polish
 
 You can send me translations in other languages
 
### Old Version

[Plugin YouTube for tinyMCE 3](http://magix-cjquery.com/post/2012/05/11/plugin-youtube-v1.4-pour-tinyMCE)

<pre>
This file is part of tinyMCE.
YouTube for tinyMCE
Copyright (C) 2011 - 2013  Gerits Aurelien aurelien[at]magix-dev[dot]be - contact[at]aurelien-gerits[dot]be

Redistributions of files must retain the above copyright notice.
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see .

####DISCLAIMER

Do not edit or add to this file if you wish to upgrade jimagine to newer
versions in the future. If you wish to customize jimagine for your
needs please refer to magix-dev.be for more information.
</pre>
