<?php

include('config/config.php');
if($_SESSION['RF']["verify"] != "RESPONSIVEfilemanager") die('Access Denied!');
include('include/utils.php');

if (isset($_SESSION['RF']['language_file']) && file_exists($_SESSION['RF']['language_file'])){
	include($_SESSION['RF']['language_file']);
}
else {
	die('Language file is missing!');
}

if(isset($_GET['action'])) 
{
    switch($_GET['action']) 
    {
		case 'view':
		    if(isset($_GET['type'])) {
				$_SESSION['RF']["view_type"] = $_GET['type'];
			}
			else {
				die('view type number missing');
			}
			break;
		case 'sort':
			if(isset($_GET['sort_by'])) {
				$_SESSION['RF']["sort_by"] = $_GET['sort_by'];
			}
			
			if(isset($_GET['descending'])) {
				$_SESSION['RF']["descending"] = $_GET['descending'] === "TRUE";
			}
			break;
		case 'image_size': // not used
	    	$pos = strpos($_POST['path'],$upload_dir);
			if ($pos !== FALSE) 
			{
				$info=getimagesize(substr_replace($_POST['path'],$current_path,$pos,strlen($upload_dir)));
				echo json_encode($info);
			}
	    	break;
		case 'save_img':
		    $info=pathinfo($_POST['name']);

		    if (strpos($_POST['path'], '/') === 0
			|| strpos($_POST['path'], '../') !== FALSE
			|| strpos($_POST['path'], './') === 0
			|| strpos($_POST['url'], 'http://featherfiles.aviary.com/') !== 0
			|| $_POST['name'] != fix_filename($_POST['name'], $transliteration)
			|| !in_array(strtolower($info['extension']), array('jpg','jpeg','png')))
		    {
			    die('wrong data');
			}

		    $image_data = get_file_by_url($_POST['url']);
		    if ($image_data === FALSE) 
		    {
		        die(lang_Aviary_No_Save);
		    }

		    file_put_contents($current_path.$_POST['path'].$_POST['name'],$image_data);

		    create_img_gd($current_path.$_POST['path'].$_POST['name'], $thumbs_base_path.$_POST['path'].$_POST['name'], 122, 91);
		    // TODO something with this function cause its blowing my mind
		    new_thumbnails_creation($current_path.$_POST['path'],$current_path.$_POST['path'].$_POST['name'],$_POST['name'],$current_path,$relative_image_creation,$relative_path_from_current_pos,$relative_image_creation_name_to_prepend,$relative_image_creation_name_to_append,$relative_image_creation_width,$relative_image_creation_height,$fixed_image_creation,$fixed_path_from_filemanager,$fixed_image_creation_name_to_prepend,$fixed_image_creation_to_append,$fixed_image_creation_width,$fixed_image_creation_height);
		    break;
		case 'extract':
		    if(strpos($_POST['path'],'/')===0 || strpos($_POST['path'],'../')!==FALSE || strpos($_POST['path'],'./')===0) {
				die('wrong path');
			}

		    $path = $current_path.$_POST['path'];
		    $info = pathinfo($path);
		    $base_folder = $current_path.fix_dirname($_POST['path'])."/";

		    switch($info['extension'])
		    {
				case "zip":
				    $zip = new ZipArchive;
				    if ($zip->open($path) === TRUE) {
						//make all the folders
						for($i = 0; $i < $zip->numFiles; $i++) 
						{ 
						    $OnlyFileName = $zip->getNameIndex($i);
						    $FullFileName = $zip->statIndex($i);    
						    if (substr($FullFileName['name'], -1, 1) =="/")
						    {
								create_folder($base_folder.$FullFileName['name']);
						    }
						}
						//unzip into the folders
						for($i = 0; $i < $zip->numFiles; $i++) 
						{ 
						    $OnlyFileName = $zip->getNameIndex($i);
						    $FullFileName = $zip->statIndex($i);    
					    
						    if (!(substr($FullFileName['name'], -1, 1) =="/"))
						    {
								$fileinfo = pathinfo($OnlyFileName);
								if(in_array(strtolower($fileinfo['extension']),$ext))
								{
								    copy('zip://'. $path .'#'. $OnlyFileName , $base_folder.$FullFileName['name'] ); 
								} 
						    }
						}
						$zip->close();
				    }
				    else {
						die(lang_Zip_No_Extract);
				    }

				    break;

				case "gz":
				    $p = new PharData($path);
				    $p->decompress(); // creates files.tar

				    break;

				case "tar":
				    // unarchive from the tar
				    $phar = new PharData($path);
				    $phar->decompressFiles();
				    $files = array();
				    check_files_extensions_on_phar( $phar, $files, '', $ext );
				    $phar->extractTo( $current_path.fix_dirname( $_POST['path'] )."/", $files, TRUE );

				    break;

				default:
					die(lang_Zip_Invalid);
		    }
		    break;
		case 'media_preview':    
			$preview_file = $_GET["file"];
			$info = pathinfo($preview_file);
			?>
			<div id="jp_container_1" class="jp-video " style="margin:0 auto;">
			    <div class="jp-type-single">
			      <div id="jquery_jplayer_1" class="jp-jplayer"></div>
			      <div class="jp-gui">
			        <div class="jp-video-play">
			          <a href="javascript:;" class="jp-video-play-icon" tabindex="1">play</a>
			        </div>
			        <div class="jp-interface">
			          <div class="jp-progress">
			            <div class="jp-seek-bar">
			              <div class="jp-play-bar"></div>
			            </div>
			          </div>
			          <div class="jp-current-time"></div>
			          <div class="jp-duration"></div>
			          <div class="jp-controls-holder">
			            <ul class="jp-controls">
			              <li><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
			              <li><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
			              <li><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
			              <li><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
			              <li><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
			              <li><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
			            </ul>
			            <div class="jp-volume-bar">
			              <div class="jp-volume-bar-value"></div>
			            </div>
			            <ul class="jp-toggles">
			              <li><a href="javascript:;" class="jp-full-screen" tabindex="1" title="full screen">full screen</a></li>
			              <li><a href="javascript:;" class="jp-restore-screen" tabindex="1" title="restore screen">restore screen</a></li>
			              <li><a href="javascript:;" class="jp-repeat" tabindex="1" title="repeat">repeat</a></li>
			              <li><a href="javascript:;" class="jp-repeat-off" tabindex="1" title="repeat off">repeat off</a></li>
			            </ul>
			          </div>
			          <div class="jp-title" style="display:none;">
			            <ul>
			              <li></li>
			            </ul>
			          </div>
			        </div>
			      </div>
			      <div class="jp-no-solution">
			        <span>Update Required</span>
			        To play the media you will need to either update your browser to a recent version or update your <a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>.
			      </div>
			    </div>
			  </div>
			<?php
			if(in_array(strtolower($info['extension']), $ext_music)) {
			?>

				<script type="text/javascript">
				    $(document).ready(function(){
						
				      $("#jquery_jplayer_1").jPlayer({
				        ready: function () {
				          $(this).jPlayer("setMedia", {
					    title:"<?php $_GET['title']; ?>",  
				            mp3: "<?php echo $preview_file; ?>",
				            m4a: "<?php echo $preview_file; ?>",
					    oga: "<?php echo $preview_file; ?>",
				            wav: "<?php echo $preview_file; ?>"
				          });
				        },
				        swfPath: "js",
					solution:"html,flash",
				        supplied: "mp3, m4a, midi, mid, oga,webma, ogg, wav",
					smoothPlayBar: true,
					keyEnabled: false
				      });
				    });
				  </script>

			<?php
			} elseif(in_array(strtolower($info['extension']), $ext_video)) {
			?>
			    
			    <script type="text/javascript">
			    $(document).ready(function(){
					
			      $("#jquery_jplayer_1").jPlayer({
			        ready: function () {
			          $(this).jPlayer("setMedia", {
				    title:"<?php $_GET['title']; ?>",  
			            m4v: "<?php echo $preview_file; ?>",
			            ogv: "<?php echo $preview_file; ?>"
			          });
			        },
			        swfPath: "js",
				solution:"html,flash",
			        supplied: "mp4, m4v, ogv, flv, webmv, webm",
				smoothPlayBar: true,
				keyEnabled: false
			    });
				  
			    });
			  </script>
			    
			<?php
			}
			break;
		case 'copy_cut':
			if ($_POST['sub_action'] != 'copy' && $_POST['sub_action'] != 'cut') {
				die('wrong sub-action');
			}

			if (trim($_POST['path']) == '' || trim($_POST['path_thumb']) == '') {
				die('no path');
			}

			$path = $current_path.$_POST['path'];

			if (is_dir($path))
			{
				// can't copy/cut dirs
				if ($copy_cut_dirs === FALSE){
					die(sprintf(lang_Copy_Cut_Not_Allowed, ($_POST['sub_action'] == 'copy' ? lcfirst(lang_Copy) : lcfirst(lang_Cut)), lang_Folders));
				}

				// size over limit
				if ($copy_cut_max_size !== FALSE && is_int($copy_cut_max_size)){
					if (($copy_cut_max_size * 1024 * 1024) < foldersize($path)){
						die(sprintf(lang_Copy_Cut_Size_Limit, ($_POST['sub_action'] == 'copy' ? lcfirst(lang_Copy) : lcfirst(lang_Cut)), $copy_cut_max_size));
					}
				}

				// file count over limit
				if ($copy_cut_max_count !== FALSE && is_int($copy_cut_max_count)){
					if ($copy_cut_max_count < filescount($path)){
						die(sprintf(lang_Copy_Cut_Count_Limit, ($_POST['sub_action'] == 'copy' ? lcfirst(lang_Copy) : lcfirst(lang_Cut)), $copy_cut_max_count));
					}
				}
			}
			else {
				// can't copy/cut files
				if ($copy_cut_files === FALSE){
					die(sprintf(lang_Copy_Cut_Not_Allowed, ($_POST['sub_action'] == 'copy' ? lcfirst(lang_Copy) : lcfirst(lang_Cut)), lang_Files));
				}
			}

			$_SESSION['RF']['clipboard']['path'] = $_POST['path'];
			$_SESSION['RF']['clipboard']['path_thumb'] = $_POST['path_thumb'];
			$_SESSION['RF']['clipboard_action'] = $_POST['sub_action'];
			break;
		case 'clear_clipboard':
			$_SESSION['RF']['clipboard'] = NULL;
			$_SESSION['RF']['clipboard_action'] = NULL;
			break;
	    default: die('no action passed');
    }
}
else 
{
	die('no action passed');
}

?>