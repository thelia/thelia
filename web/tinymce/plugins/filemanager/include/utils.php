<?php 

if($_SESSION["verify"] != "RESPONSIVEfilemanager") die('forbiden');

function deleteDir($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDir($dir.DIRECTORY_SEPARATOR.$item)) return false;
    }
    return rmdir($dir);
}

function rename_file($old_path,$name){
    if(file_exists($old_path)){
	$info=pathinfo($old_path);
	$new_path=$info['dirname']."/".$name.".".$info['extension'];
	if(file_exists($new_path)) return false;
	return rename($old_path,$new_path);
    }
}

function rename_folder($old_path,$name){
    $name=fix_filename($name);
    if(file_exists($old_path)){
	$new_path=fix_dirname($old_path)."/".$name;
	if(file_exists($new_path)) return false;
	return rename($old_path,$new_path);
    }
}

function create_img_gd($imgfile, $imgthumb, $newwidth, $newheight="") {
    require_once('php_image_magician.php');
    
    $magicianObj = new imageLib($imgfile);
    // *** Resize to best fit then crop
    $magicianObj -> resizeImage($newwidth, $newheight, 'crop');
    
    $magicianObj -> saveImage($imgthumb,80);
}

function create_img($imgfile, $imgthumb, $newwidth, $newheight) {
    require_once('php_image_magician.php');  
    $magicianObj = new imageLib($imgfile);
    $magicianObj -> resizeImage($newwidth, $newheight, 'auto');  
    $magicianObj -> saveImage($imgthumb,80);
}

function makeSize($size) {
   $units = array('B','KB','MB','GB','TB');
   $u = 0;
   while ( (round($size / 1024) > 0) && ($u < 4) ) {
     $size = $size / 1024;
     $u++;
   }
   return (number_format($size, 0) . " " . $units[$u]);
}

function foldersize($path) {
    $total_size = 0;
    $files = scandir($path);
    $cleanPath = rtrim($path, '/'). '/';

    foreach($files as $t) {
        if ($t<>"." && $t<>"..") {
            $currentFile = $cleanPath . $t;
            if (is_dir($currentFile)) {
                $size = foldersize($currentFile);
                $total_size += $size;
            }
            else {
                $size = filesize($currentFile);
                $total_size += $size;
            }
        }   
    }

    return $total_size;
}

function create_folder($path=false,$path_thumbs=false){
    $oldumask = umask(0);
    if ($path && !file_exists($path))
        mkdir($path, 0777, true); // or even 01777 so you get the sticky bit set 
    if($path_thumbs && !file_exists($path_thumbs)) 
        mkdir($path_thumbs, 0777, true) or die("$path_thumbs cannot be found"); // or even 01777 so you get the sticky bit set 
    umask($oldumask);
}

function check_files_extensions_on_path($path,$ext){
    if(!is_dir($path)){
	$fileinfo = pathinfo($path);
	if(!in_array($fileinfo['extension'],$ext))
	    unlink($path);
    }else{
	$files = scandir($path);
	foreach($files as $file){
	    check_files_extensions_on_path(trim($path,'/')."/".$file,$ext);
	}
    }
}

function fix_filename($str){
    $str = iconv('UTF-8', 'US-ASCII//TRANSLIT', $str);
    $str = preg_replace("/[^a-zA-Z0-9\.\[\]_| -]/", '', $str);
    $str = mb_strtolower(trim($str));
    
    return $str;
}

function fix_dirname($str){
    return str_replace('~',' ',dirname(str_replace(' ','~',$str)));
}

function fix_path($path){
    $info=pathinfo($path);
    $tmp_path=$info['dirname'];
    $str=fix_filename($info['filename']);
    if($tmp_path!="")
	return $tmp_path.DIRECTORY_SEPARATOR.$str;
    else
	return $str;
}

function base_url(){
  return sprintf(
    "%s://%s",
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
    $_SERVER['HTTP_HOST']
  );
}

function config_loading($current_path,$fld){
    if(file_exists($current_path.$fld.".config")){
	require_once($current_path.$fld.".config");
	return true;
    }
    echo "!!!!".$parent=fix_dirname($fld);
    if($parent!="." && !empty($parent)){
	config_loading($current_path,$parent);
    }
    
    return false;
}

function endsWith($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function new_thumbnails_creation($targetPath,$targetFile,$name,$current_path,$relative_image_creation,$relative_path_from_current_pos,$relative_image_creation_name_to_prepend,$relative_image_creation_name_to_append,$relative_image_creation_width,$relative_image_creation_height,$fixed_image_creation,$fixed_path_from_filemanager,$fixed_image_creation_name_to_prepend,$fixed_image_creation_to_append,$fixed_image_creation_width,$fixed_image_creation_height){
    //create relative thumbs
    if($relative_image_creation){
	foreach($relative_path_from_current_pos as $k=>$path){
	    if($path!="" && $path[strlen($path)-1]!="/") $path.="/";
	    if (!file_exists($targetPath.$path)) create_folder($targetPath.$path,false);
	    $info=pathinfo($name);
	    if(!endsWith($targetPath,$path))
		create_img($targetFile, $targetPath.$path.$relative_image_creation_name_to_prepend[$k].$info['filename'].$relative_image_creation_name_to_append[$k].".".$info['extension'], $relative_image_creation_width[$k], $relative_image_creation_height[$k]);
	}
    }
    
    //create fixed thumbs
    if($fixed_image_creation){
	foreach($fixed_path_from_filemanager as $k=>$path){
	    if($path!="" && $path[strlen($path)-1]!="/") $path.="/";
	    $base_dir=$path.substr_replace($targetPath, '', 0, strlen($current_path));
	    if (!file_exists($base_dir)) create_folder($base_dir,false);
	    $info=pathinfo($name);
	    create_img($targetFile, $base_dir.$fixed_image_creation_name_to_prepend[$k].$info['filename'].$fixed_image_creation_to_append[$k].".".$info['extension'], $fixed_image_creation_width[$k], $fixed_image_creation_height[$k]);
	}
    }
}

?>