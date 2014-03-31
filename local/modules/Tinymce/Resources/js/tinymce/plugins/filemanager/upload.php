<?php
include('config/config.php');
if($_SESSION["verify"] != "RESPONSIVEfilemanager") die('forbiden');
include('include/utils.php');


$storeFolder = $_POST['path'];
$storeFolderThumb = $_POST['path_thumb'];

$path_pos=strpos($storeFolder,$current_path);
$thumb_pos=strpos($_POST['path_thumb'],$thumbs_base_path);
if($path_pos!=0 
    || $thumb_pos !=0
    || strpos($_POST['path_thumb'],'../',strlen($thumbs_base_path)+$thumb_pos)!==FALSE
    || strpos($storeFolder,'../',strlen($current_path)+$path_pos)!==FALSE
    || strpos($storeFolder,'./',strlen($current_path)+$path_pos)!==FALSE )
    die('wrong path');


$path=$storeFolder;
$cycle=true;
$max_cycles=50;
$i=0;
while($cycle && $i<$max_cycles){
    $i++;
    if($path==$current_path)  $cycle=false;
    if(file_exists($path."config.php")){
	require_once($path."config.php");
	$cycle=false;
    }
    $path=fix_dirname($path).'/';
}


if (!empty($_FILES)) {
$info=pathinfo($_FILES['file']['name']);
if(in_array($info['extension'], $ext)){
    $tempFile = $_FILES['file']['tmp_name'];   
      
    $targetPath = $storeFolder;
    $targetPathThumb = $storeFolderThumb;
    $_FILES['file']['name'] = fix_filename($_FILES['file']['name']);
     
    if(file_exists($targetPath.$_FILES['file']['name'])){
	$i = 1;
	$info=pathinfo($_FILES['file']['name']);
	while(file_exists($targetPath.$info['filename'].".[".$i."].".$info['extension'])) {
		$i++;
	}
	$_FILES['file']['name']=$info['filename'].".[".$i."].".$info['extension'];
    }
    $targetFile =  $targetPath. $_FILES['file']['name']; 
    $targetFileThumb =  $targetPathThumb. $_FILES['file']['name'];

    move_uploaded_file($tempFile,$targetFile);
    chmod($targetFile, 0755);
    if(in_array(substr(strrchr($_FILES['file']['name'],'.'),1),$ext_img)) $is_img=true;
    else $is_img=false;

    if($is_img){
	create_img_gd($targetFile, $targetFileThumb, 122, 91);
	
	new_thumbnails_creation($targetPath,$targetFile,$_FILES['file']['name'],$current_path,$relative_image_creation,$relative_path_from_current_pos,$relative_image_creation_name_to_prepend,$relative_image_creation_name_to_append,$relative_image_creation_width,$relative_image_creation_height,$fixed_image_creation,$fixed_path_from_filemanager,$fixed_image_creation_name_to_prepend,$fixed_image_creation_to_append,$fixed_image_creation_width,$fixed_image_creation_height);
	
	$imginfo =getimagesize($targetFile);
	$srcWidth = $imginfo[0];
	$srcHeight = $imginfo[1];
	
	
	if($image_resizing){
	    if($image_resizing_width==0){
		if($image_resizing_height==0){
		    $image_resizing_width=$srcWidth;
		    $image_resizing_height =$srcHeight;
		}else{
		    $image_resizing_width=$image_resizing_height*$srcWidth/$srcHeight;
	    }
	    }elseif($image_resizing_height==0){
		$image_resizing_height =$image_resizing_width*$srcHeight/$srcWidth;
	    }
	    $srcWidth=$image_resizing_width;
	    $srcHeight=$image_resizing_height;
	    create_img_gd($targetFile, $targetFile, $image_resizing_width, $image_resizing_height);
	}
	
	//max resizing limit control
	$resize=false;
	if($image_max_width!=0 && $srcWidth >$image_max_width){
	    $resize=true;
	    $srcHeight=$image_max_width*$srcHeight/$srcWidth;
	    $srcWidth=$image_max_width;
	}
	
	if($image_max_height!=0 && $srcHeight >$image_max_height){
	    $resize=true;
	    $srcWidth =$image_max_height*$srcWidth/$srcHeight;
	    $srcHeight =$image_max_height;
	}
	if($resize)
	    create_img_gd($targetFile, $targetFile, $srcWidth, $srcHeight);	
	
    }
}else{
    echo "file not permitted";
}
}else{
    echo "error";
}
if(isset($_POST['submit'])){
    $query = http_build_query(array(
        'type'      => $_POST['type'],
        'lang'      => $_POST['lang'],
        'popup'     => $_POST['popup'],
        'field_id'  => $_POST['field_id'],
        'fldr'      => $_POST['fldr'],
    ));
    header("location: dialog.php?" . $query);
}

?>      
