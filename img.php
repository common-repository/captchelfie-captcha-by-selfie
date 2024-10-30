<?php 
//die(__FILE__);
//we find filename by hash, and tummel the image output_add_rewrite_var
//called from the wp-content/uploads/facedetect/index.php
//we have to hide file real name for privacy - it looks like UNIXTIME_IP_USER.jpg and contains picture. 
//Nobody's business who loggen when, rignt? Well, except of admin, he/she can see the files
//If user does not like it - avoidthe CAPTELFIE
//TODO - we probably shall inflate image twice to make it sharable in every network even for old cameras, https://blog.bufferapp.com/ideal-image-sizes-social-media-posts
//error_reporting(0);
function captchelfie_image_out($img_dir)
{
//print_r($_GET);

	if(isset($_GET['captchelfie'])) //version compatibility , img1, 1 - version 1
	{
//echo("$img_dir");
		$img1_hash = $_GET['captchelfie'];
		$all_files = scandir($img_dir,1); //1 - most likely it's fresh file
		foreach($all_files as $filename)
		{
			$captchelfie_attrs = explode('_',$filename);
			if(count($captchelfie_attrs) > 2)
			{
				$captchelfie_seconds = $captchelfie_attrs[0];
				$captchelfie_ip = $captchelfie_attrs[1];
				$current_hash = sha1($captchelfie_seconds . $captchelfie_ip);//unique 
				if($img1_hash == $current_hash)//we done here - found ours
				{	
//echo("$filename<br>\n $current_hash<br>\n$img1_hash<br>\n");	die();						
					$full_filename = $img_dir . DIRECTORY_SEPARATOR . $filename;
					header('Pragma: public'); //cache - reduces  load to the web server 
					header('Cache-Control: max-age=86400');   //cache - reduces  load to the web server 
					header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));  //cache - reduces  load to the web server 
					
					header("Content-Type: image/jpg");//we deal with JPG only
					
					if(0) //spit out as is, no resizing
					{
						$fp = fopen($full_filename, 'rb');
						header("Content-Length: " . filesize($full_filename) );
						fpassthru($fp);	
					}
					else //we try to inflate it twice - better for most of social networkss
					{
						$image = imagecreatefromjpeg($full_filename);
						$width  = imagesx($image);
						$height = imagesy($image);
						$new_width =  $width *  2;
						$new_height = $height * 2;
						// resize image
						$new_image = imagecreatetruecolor($new_width, $new_height);
						imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
						// get size of resized image
						ob_start();
						// put output for image in buffer
						imagejpeg($new_image);
						// get size of output
						$size = ob_get_length();
						// set correct header
						header("Content-Length: " . $size);
						// flush the buffer, actually send the output to the browser
						ob_end_flush();
						// destroy resources
						imagedestroy($new_image);
						imagedestroy($image);						
					}
					exit(); //we done
				}//hash match
			}
		}//foreach
	}
	else //no such tag should never happened in normal usage
	{
		header("Location: https://api.bio/");
	}
}	//captchelfie_image_out

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) //we are called directly
{
	$our_dir = dirname( __FILE__ );
	$pic_dir = substr($our_dir,0,strpos($our_dir,'wp-content') ) . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'facedetect';
	captchelfie_image_out($pic_dir);
}


