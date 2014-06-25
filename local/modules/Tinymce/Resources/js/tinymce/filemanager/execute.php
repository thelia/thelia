<?php
include('config/config.php');
if ($_SESSION['RF']["verify"] != "RESPONSIVEfilemanager") die('forbiden');
include('include/utils.php');


$thumb_pos  = strpos($_POST['path_thumb'], $thumbs_base_path);

if ($thumb_pos !=0
    || strpos($_POST['path_thumb'],'../',strlen($thumbs_base_path)+$thumb_pos)!==FALSE
    || strpos($_POST['path'],'/')===0
    || strpos($_POST['path'],'../')!==FALSE
    || strpos($_POST['path'],'./')===0)
{
    die('wrong path');
}

$language_file = 'lang/'.$default_language.'.php'; 
if (isset($_GET['lang']) && $_GET['lang'] != 'undefined' && $_GET['lang']!='') 
{
    $path_parts = pathinfo($_GET['lang']);
    if (is_readable('lang/' .$path_parts['basename']. '.php'))
    { 
        $language_file = 'lang/' .$path_parts['basename']. '.php';
    }
}

require_once $language_file;

$base = $current_path;
$path = $current_path.$_POST['path'];
$cycle = TRUE;
$max_cycles = 50;
$i = 0;
while($cycle && $i<$max_cycles)
{
    $i++;
    if ($path == $base)  $cycle=FALSE;
    
    if (file_exists($path."config.php"))
    {
	   require_once($path."config.php");
	   $cycle = FALSE;
    }
    $path = fix_dirname($path)."/";
    $cycle = FALSE;
}

$path = $current_path.$_POST['path'];
$path_thumb = $_POST['path_thumb'];
if (isset($_POST['name']))
{
    $name = $_POST['name'];
    if (strpos($name,'../') !== FALSE) die('wrong name');
}

$info = pathinfo($path);
if (isset($info['extension']) && !(isset($_GET['action']) && $_GET['action']=='delete_folder') && !in_array(strtolower($info['extension']), $ext))
{
    die('wrong extension');
}
    
if (isset($_GET['action']))
{
    switch($_GET['action'])
    {
        case 'delete_file':
            if ($delete_files){
                unlink($path);
                if (file_exists($path_thumb)) unlink($path_thumb);
		    
        		$info=pathinfo($path);
        		if ($relative_image_creation){
        		    foreach($relative_path_from_current_pos as $k=>$path)
                    {
                        if ($path!="" && $path[strlen($path)-1]!="/") $path.="/";

            			if (file_exists($info['dirname']."/".$path.$relative_image_creation_name_to_prepend[$k].$info['filename'].$relative_image_creation_name_to_append[$k].".".$info['extension']))
                        {
            			    unlink($info['dirname']."/".$path.$relative_image_creation_name_to_prepend[$k].$info['filename'].$relative_image_creation_name_to_append[$k].".".$info['extension']);
            			}
        		    }
        		}
        		
        		if ($fixed_image_creation)
                {
        		    foreach($fixed_path_from_filemanager as $k=>$path)
                    {
            			if ($path!="" && $path[strlen($path)-1] != "/") $path.="/";

            			$base_dir=$path.substr_replace($info['dirname']."/", '', 0, strlen($current_path));
            			if (file_exists($base_dir.$fixed_image_creation_name_to_prepend[$k].$info['filename'].$fixed_image_creation_to_append[$k].".".$info['extension']))
                        {
            			    unlink($base_dir.$fixed_image_creation_name_to_prepend[$k].$info['filename'].$fixed_image_creation_to_append[$k].".".$info['extension']);
            			}
        		    }
        		}
            }
            break;
        case 'delete_folder':
            if ($delete_folders){
        		if (is_dir($path_thumb))
                {
        		    deleteDir($path_thumb);
                }

        		if (is_dir($path))
                {
        		    deleteDir($path);	
        		    if ($fixed_image_creation)
                    {
            			foreach($fixed_path_from_filemanager as $k=>$paths){
            			    if ($paths!="" && $paths[strlen($paths)-1] != "/") $paths.="/";

            			    $base_dir=$paths.substr_replace($path, '', 0, strlen($current_path));
            			    if (is_dir($base_dir)) deleteDir($base_dir);
            			}
        		    }
        		}
            }
            break;
        case 'create_folder':
            if ($create_folders)
            {
                create_folder(fix_path($path,$transliteration),fix_path($path_thumb,$transliteration));
            }
            break;
        case 'rename_folder':
            if ($rename_folders){
                $name=fix_filename($name,$transliteration);
                $name=str_replace('.','',$name);
		
                if (!empty($name)){
                    if (!rename_folder($path,$name,$transliteration)) die(lang_Rename_existing_folder);

                    rename_folder($path_thumb,$name,$transliteration);
        		    if ($fixed_image_creation){
            			foreach($fixed_path_from_filemanager as $k=>$paths){
            			    if ($paths!="" && $paths[strlen($paths)-1] != "/") $paths.="/";
            			    
                            $base_dir=$paths.substr_replace($path, '', 0, strlen($current_path));
            			    rename_folder($base_dir,$name,$transliteration);
            			}
		           }
                }
                else {
                    die(lang_Empty_name);
                }
            }
            break;
        case 'rename_file':
            if ($rename_files){
                $name=fix_filename($name,$transliteration);
                if (!empty($name))
                {
                    if (!rename_file($path,$name,$transliteration)) die(lang_Rename_existing_file);

                    rename_file($path_thumb,$name,$transliteration);

        		    if ($fixed_image_creation)
                    {
                        $info=pathinfo($path);

            			foreach($fixed_path_from_filemanager as $k=>$paths)
                        {
            			    if ($paths!="" && $paths[strlen($paths)-1] != "/") $paths.="/";

            			    $base_dir = $paths.substr_replace($info['dirname']."/", '', 0, strlen($current_path));
            			    if (file_exists($base_dir.$fixed_image_creation_name_to_prepend[$k].$info['filename'].$fixed_image_creation_to_append[$k].".".$info['extension']))
                            {
            				    rename_file($base_dir.$fixed_image_creation_name_to_prepend[$k].$info['filename'].$fixed_image_creation_to_append[$k].".".$info['extension'],$fixed_image_creation_name_to_prepend[$k].$name.$fixed_image_creation_to_append[$k],$transliteration);
            			    }
            			}
        		    }
                }
                else {
                    die(lang_Empty_name);
                }
            }
            break;
	   case 'duplicate_file':
            if ($duplicate_files)
            {
                $name = fix_filename($name,$transliteration);
                if (!empty($name))
                {
                    if (!duplicate_file($path,$name)) die(lang_Rename_existing_file);
                    
                    duplicate_file($path_thumb,$name);
        		    
                    if ($fixed_image_creation)
                    {
                        $info=pathinfo($path);
            			foreach($fixed_path_from_filemanager as $k=>$paths)
                        {
            			    if ($paths!="" && $paths[strlen($paths)-1] != "/") $paths.= "/";

            			    $base_dir=$paths.substr_replace($info['dirname']."/", '', 0, strlen($current_path));

            			    if (file_exists($base_dir.$fixed_image_creation_name_to_prepend[$k].$info['filename'].$fixed_image_creation_to_append[$k].".".$info['extension']))
                            {
            				duplicate_file($base_dir.$fixed_image_creation_name_to_prepend[$k].$info['filename'].$fixed_image_creation_to_append[$k].".".$info['extension'],$fixed_image_creation_name_to_prepend[$k].$name.$fixed_image_creation_to_append[$k]);
            			    }
            			}
        		    }
                }
                else
                {
                    die(lang_Empty_name);
                }
            }
            break;
        case 'paste_clipboard':
            if ( ! isset($_SESSION['RF']['clipboard_action'], $_SESSION['RF']['clipboard']['path'], $_SESSION['RF']['clipboard']['path_thumb']) 
                || $_SESSION['RF']['clipboard_action'] == '' 
                || $_SESSION['RF']['clipboard']['path'] == ''
                || $_SESSION['RF']['clipboard']['path_thumb'] == '')
            {
                die();
            }

            $action = $_SESSION['RF']['clipboard_action'];
            $data = $_SESSION['RF']['clipboard'];
            $data['path'] = $current_path.$data['path'];
            $pinfo = pathinfo($data['path']);
            
            // user wants to paste to the same dir. nothing to do here...
            if ($pinfo['dirname'] == rtrim($path, '/')) {
                die();
            }

            // user wants to paste folder to it's own sub folder.. baaaah.
            if (is_dir($data['path']) && strpos($path, $data['path']) !== FALSE){
                die();
            } 

            // something terribly gone wrong
            if ($action != 'copy' && $action != 'cut'){
                die('no action');
            }

            // check for writability
            if (is_really_writable($path) === FALSE || is_really_writable($path_thumb) === FALSE){
                die($path.'--'.$path_thumb.'--'.lang_Dir_No_Write);
            }

            // check if server disables copy or rename
            if (is_function_callable(($action == 'copy' ? 'copy' : 'rename')) === FALSE){
                die(sprintf(lang_Function_Disabled, ($action == 'copy' ? lcfirst(lang_Copy) : lcfirst(lang_Cut))));
            }

            if ($action == 'copy')
            {
                rcopy($data['path'], $path);
                rcopy($data['path_thumb'], $path_thumb);
            }
            elseif ($action == 'cut')
            {
                rrename($data['path'], $path);
                rrename($data['path_thumb'], $path_thumb);

                // cleanup
                if (is_dir($data['path']) === TRUE){
                    rrename_after_cleaner($data['path']);
                    rrename_after_cleaner($data['path_thumb']);
                }
            }

            // cleanup
            $_SESSION['RF']['clipboard']['path'] = NULL;
            $_SESSION['RF']['clipboard']['path_thumb'] = NULL;
            $_SESSION['RF']['clipboard_action'] = NULL;

            break;
        default:
            die('wrong action');
    }  
}

?>