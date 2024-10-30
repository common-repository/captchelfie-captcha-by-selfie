<?php 
/*
 * Plugin Name: API.BIO Captchelfie - Captcha by Selfie
 * Plugin URI: https://api.bio/captchelfie
 * Description: Captchelfie - Captcha by Selfie. Now with social capabilities.
 * Author: Alexey Trofimov
 * Version: 1.0.7
 * Author URI: http://api.bio
 * Text Domain: captchelfie
 * Domain Path: /languages
 * License: GPLv2 
*/

/*
This plugin interacts with (and uses code samples of) :
    Contact Form by BestWebSoft @ Copyright 2016  BestWebSoft  ( http://support.bestwebsoft.com )
    Google Captcha (reCAPTCHA) by BestWebSoft © Copyright 2016  BestWebSoft  ( http://support.bestwebsoft.com )
    Captcha by BestWebSoft  © Copyright 2016  BestWebSoft  ( http://support.bestwebsoft.com )
*/


error_reporting(E_ERROR); /*gonna show errors - in case some package is absent */

require_once( dirname( __FILE__ ) . '/captchelfie-config.php' ); //global  $apibio_captchelfie_supported; 


class apibio_captchelfie
{
	
	function apibio_captchelfie_add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url('/options-general.php?page=captchelfie') . '">' . __( 'Settings' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	
	function __construct()	
    {
//delete_option('captchelfie');	//clear settings - debug	
//print_r(get_option('captchelfie')); //debug info 
		global $apibio_captchelfie_default_options;
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
//ajax
		add_action('wp_ajax_captchelfie_unique_action',array($this,'doAjax'));					
		add_action('wp_ajax_nopriv_captchelfie_unique_action',array($this,'doAjax'));		
		
		add_action('wp_head', array( &$this,'apibio_captchelfie_head'),11); //11 - priority, hopefully we gonna  be the last
		add_action('login_enqueue_scripts', array( &$this,'apibio_captchelfie_head'),11); //wp_head is theme-dependent   
	
		
		add_filter( "plugin_action_links_".plugin_basename( __FILE__ ), array( $this,'apibio_captchelfie_add_settings_link') );	

		add_shortcode( 'CAPTCHELFIE_PREVIEW', array( &$this,'apibio_preview_create') ); //just make adding preview easily
		
		$form_priority = 10; //default 10  -same place where the standard captha is 
		$check_priority = -10; //default 10 - we have to fire first
		
		$val = get_option('captchelfie'); 
		
		if ( '1' ==  $val['l-form'] ) { //login
			add_action( 'login_form',  array( &$this,'apibio_captchelfie_display'),$form_priority );
			add_action( 'authenticate',  array( &$this,'apibio_captchelfie_check'), $check_priority );
		}


		if ( '1' == $val['rp-form'] ) { //lost password
			add_action( 'lostpassword_form',  array( &$this,'apibio_captchelfie_display'),$form_priority );
			add_action( 'allow_password_reset', array( &$this,'apibio_captchelfie_check'), $check_priority );
		}

		if ( '1' == $val['r-form']  ) {//register
			add_action( 'register_form',  array( &$this,'apibio_captchelfie_display'),$form_priority );
			add_action( 'registration_errors', array( &$this,'apibio_captchelfie_check'), $check_priority ); 
		}


		if ( '1' == $val['c-form']  ) //comment
		{
			add_action( 'comment_form_after_fields',  array( &$this,'apibio_captchelfie_display') ,$form_priority);
			add_action( 'comment_form_logged_in_after',  array( &$this,'apibio_captchelfie_display' ),$form_priority);
			add_action( 'pre_comment_on_post', array( &$this,'apibio_captchelfie_check'), $check_priority );			
		}


		$cf_form_priority = 15; //just make it different - before the original, so we are called after the original
		if ( '1' == $val['cf-form']  ) {//contact forms   BestSoft, form-7
			add_filter( 'cntctfrm_display_captcha', array( &$this,'apibio_captchelfie_return_content'), $cf_form_priority ,2); //contact form   BestSoft
			add_filter( 'cntctfrmpr_display_captcha', array( &$this,'apibio_captchelfie_return_content'), $cf_form_priority,2); //contact form   BestSoft
			add_filter( 'cntctfrm_check_form', array( &$this,'apibio_captchelfie_check'), $check_priority ); //contact form   BestSoft
			add_filter( 'cntctfrmpr_check_form', array( &$this,'apibio_captchelfie_check'), $check_priority );  //contact form   BestSoft

//CONTACT FORM 7 - work in progress			
//			add_filter( 'the_content', array( &$this,'apibio_captchelfie_content_filter'),$form_priority + 100); //FIRE THE LAST ONE (+100) going to search for Form 7 content - dirty but effective
//			add_filter( 'wpcf7_spam',  array( &$this,'apibio_captchelfie_check') ,$check_priority);
			
		}
	}//EOF __construct()
	
	
	function  apibio_captchelfie_content_filter($content) //fire at 'the_content', searching for 'Form 7' stuff
	{
		$tag = 'class="wpcf7-form-control wpcf7-submit" />'; //Contact Form 7
		$form7_sumbit_pos = strpos($content,$tag); //we are searching for unique classes inside '<input' tag
		if($form7_sumbit_pos !== FALSE) //found. 
		{
			$html_before_f7_class = substr($content,0,$form7_sumbit_pos);
			$form7_sumbit_pos = strrpos($html_before_f7_class,'<'); //right before '<input'
			$captchelfie_form_code = $this->apibio_captchelfie_get();
			$content = substr($content, 0, $form7_sumbit_pos) . $captchelfie_form_code . substr($content, $form7_sumbit_pos); //nice insert, huh? =)
		} 
		return($content); 
	}
	
	function apibio_captchelfie_head()
	{
//print_r($_GET);
		$tag = '';
		if(isset($_GET['captchelfie']) ) //easy way  -it's set
		{
			$tag = $_GET['captchelfie'];
		}
		else
		{
			if(isset($_GET['redirect_to']) ) //hard way - we did share admin url, and got redirected
			{
				$redirect_to = $_GET['redirect_to'];
				$redirect_to = urldecode($redirect_to); 
				$get_array = array();
				parse_str($redirect_to, $get_array);
				if(isset($get_array['captchelfie']))
				{
					$tag = $get_array['captchelfie'];
				}
			}
		}
//echo("||| $tag |||");		 
		if( strlen($tag) > 0 )
		{

			if( 0
				|| (strpos($_SERVER['REQUEST_URI'],'wp-login.php') !== FALSE) 
				|| (strpos($_SERVER['REQUEST_URI'],'wp-admin') !== FALSE)
			   )///nope, not that simple - FB and G+ do not allow double-jumps, no wp_redirect()!
			{
				$share_title =  get_bloginfo('name'); //main title
				echo("\n".'<meta name="og:title" content="'.$share_title.'" />');
				$share_desc = get_bloginfo('description');//main description
				echo("\n".'<meta name="og:description" content="'.$share_desc.'" />');
				echo("\n".'<script>window.location="'.get_site_url().'";</script>'); //soft jump if we are on login page and have 'captchelfie=..."
			}//if special page
			
			$self_url = add_query_arg( 'captchelfie', $tag, get_permalink() ); //always self
			$image_url = plugin_dir_url( __FILE__ ) . "img.php" . '?captchelfie=' . $tag; //return always
			echo("\n".'<meta property="og:image" content="'.$image_url.'" />');
			echo("\n".'<meta name="author" content="https://api.bio" />');
			echo("\n".'<meta property="og:url" content="'. $self_url . '" />');
			echo("\n".'<meta property="og:type" content="website" />'); //without it FB does not work
			echo("\n".'<link rel="image_src" href="'.$image_url.'" />'."\n");
			
		}
	}
//TODO - check for double load!!!!!!!!!!!!!!!	
	function apibio_captchelfie_js_scripts()//we manage included javascripts here. 
	{ 
		wp_enqueue_script("jquery");
		wp_enqueue_script( 'captchelfie_webcam.js', plugins_url( 'webcam/webcam.js', __FILE__ ));
		wp_enqueue_script( 'captchelfie_faceapiclient.js', plugins_url( 'api.bio/FaceApiClient.js', __FILE__ ));		
		//qtip support
		wp_enqueue_script( 'captchelfie_qtip.js', plugins_url( 'qtip/jquery.qtip.min.js', __FILE__ ));	
		wp_enqueue_style( 'captchelfie_qtip.css', plugins_url( 'qtip/jquery.qtip.min.css', __FILE__ ));	
		//functionality
		wp_enqueue_script( 'captchelfie.js', plugins_url( 'captchelfie.js', __FILE__ ));
		wp_enqueue_style( 'captchelfie.css', plugins_url( 'captchelfie.css', __FILE__ ));	
		//browser camera help
		wp_enqueue_script( 'captchelfie_camerahelp.js', plugins_url( 'api.bio/CameraHelp.js', __FILE__ ));

	}

	function apibio_captchelfie_configure_msg()//just for readability
	{
		$auth_domain = site_url(); 
		$auth_domain = str_replace(array('http://,','http://'),array('',''),$auth_domain );//remove protocol
		$content .= sprintf(
					'%s <a target="_blank" href="https://api.bio/keys/">%s</a> %s <a target="_blank" href="%s">%s</a>%s',
					 __( 'To use API.BIO Captchelfie you should get the application key from', 'captchelfie' ),
					__( 'API.BIO', 'captchelfie' ),
					__( ', enter it on the', 'captchelfie' ),
					admin_url( '/options-general.php?page=captchelfie' ),
					__( 'plugin setting page', 'captchelfie' ),
					__( ', and save settings. ', 'captchelfie' ) . '<br>' .
					__( '<br>Please do not forget to configure the application <strong>Authenticated domain</strong> field to <strong>', 'captchelfie' ) . '<u>'	. $auth_domain . '</u>' .'</strong>'			
				);
		return($content);
	}
		
	function apibio_captchelfie_body()//we display Captchelfie box here	
	{
		global $apibio_captchelfie_supported;
		$this->apibio_captchelfie_js_scripts();
		$js_params = "\n<script>"; //here we going to pass parameters to javascript as globals. butt-ugly though
		$js_params .= "\n var captchelfie_plugin_url='" . plugins_url( '', __FILE__ ) . "';"; //we need it to use absolute  urls if we hant to

		$supported_blocks = array();
		foreach($apibio_captchelfie_supported as $hook_system)
		{
			array_push($supported_blocks,'"'.$hook_system['block'].'"');
		}
		$js_params .= "\n var captchelfie_suppored_blocks = [" . implode(',',$supported_blocks) . "];\n";
		
		$val = get_option('captchelfie'); 
//print_r($val);		

		$js_params .= "\n var captchelfie_site_url='" . get_site_url() . "';"; 
		$js_params .= "\n var captchelfie_api_url='" . $val['apibio-api-url'] . "';"; 
		$js_params .= "\n var captchelfie_api_key='" . $val['apibio-key'] . "';"; 
		$js_params .= "\n var captchelfie_mirror_mode='" . $val['options-mirror'] . "';"; //options "" or "1"
		$js_params .= "\n var captchelfie_stop_on_success='" . $val['options-success-stop'] . "';"; //options "" or "1"
		
		$js_params .= "\n var captchelfie_auth_error='". str_replace("'","\'",$this->apibio_captchelfie_configure_msg()) . "';"; 
		$js_params .= "\n var captchelfie_is_admin=" . ( ( current_user_can( 'manage_options' )) ? 'true' : 'false') . ";"; //language	
		
		$cur_kept_seconds = $val['options-pics-keep']; //if zero are not stored, so we can not share
		if(!is_numeric ( $cur_kept_seconds ) )
		{
			$cur_kept_seconds = 60 * 60 * 24;
		}
		$js_params .= "\n var captchelfie_allow_share=" . ( ( $cur_kept_seconds > 0 ) ? 'true' : 'false') . ";"; //language		
		
		$js_params .= "\n var captchelfie_l_live='" . __( 'to Captchelfie', 'captchelfie' ) . "';"; //language 
		$js_params .= "\n var captchelfie_l_standard='" . __( 'to Standard Captcha', 'captchelfie' ) . "';"; //language		
		$js_params .= "\n var captchelfie_l_init='" . __( 'Waiting for video...', 'captchelfie' ) . "';"; //language
		$js_params .= "\n var captchelfie_l_no_camera='" . __( 'Camera is not available', 'captchelfie' ) . "';"; //language
		$js_params .= "\n var captchelfie_l_reset='" . __( 'Conduct Captchelfie', 'captchelfie' ) . "';"; //language		
		$js_params .= "\n var captchelfie_l_detect_face='" . __( 'Detect Face', 'captchelfie' ) . "';"; //language
		$js_params .= "\n var captchelfie_l_detected_ok='" . __( 'Face Detected<br><br>you are not a robot<br><br>:)', 'captchelfie' ) . "';"; //language
		$js_params .= "\n var captchelfie_l_detected_success='" . __( 'Captcha passed - you are not a robot :)', 'captchelfie' ) . "';"; //language
		$js_params .= "\n var captchelfie_l_detected_share='" . __( 'Captcha already passed - share your Selfie! (and help this website too) ', 'captchelfie' ) . "';"; //language
		$js_params .= "\n var captchelfie_l_share_title='" . __( 'Share your Selfie!', 'captchelfie' ) . "';"; //language
		$js_params .= "\n var captchelfie_l_share_ready='" . __( 'Ready to share! =)', 'captchelfie' ) . "';"; //language
		$js_params .= "\n var captchelfie_l_detected_error='" . __( 'Face not Detected<br><br>Try again', 'captchelfie' ) . "';"; //language
		

		$js_params .= "</script>\n"; //here we going to pass parameters to javascript as globals. butt-ugly though 
		return $js_params . $this->get_body_html();//just for readability
	}

	
	function get_share_body()
	{
//TODO - smart sharers hangling via cookies
		$share_services = "google,facebook,linkedin,vkontakte,tumblr,digg,googlebookmark,stumbleupon,delicious,odnoklassniki,moimir,blogger,evernote,twitter,formspring,instapaper,juick,liveinternet,livejournal,memori,pinme,springpad,surfingbird,webdiscover,yahoo,myspace,bobrdobr,vkrugu,bookmark,moikrug,moemesto,webmoney,misterwong,friendfeed,pinterest,readability,email,print,yandex,yazakladki";
		$ret = "
<div class='pluso' data-lang='".  __( 'en', 'captchelfie' ) ."' data-background='transparent' data-options='medium,square,line,horizontal,counter,theme=03' data-services='".$share_services."'></div>
		";
		return($ret);
	}
	
	
	function get_body_html()
	{
		$ret = "
	<!-- API.BIO Captchelfie START --->
	<div id='captchelfie_code_holder' style='display:none;'>   
			<div id='captchelfie_block_wrap' style='position: relative;text-align: center; '>
				<img id='captchelfie_loading' src='' class='captchelfie_middle' style='width:64px; height:64px;'></img>
				<div id='captchelfie_message' class='captchelfie_middle' style='margin: 25% 0% 25% 0%;'>*</div>			
				<div id='captchelfie_camera' style='width: 100%; height:200px;  position: relative; '></div>
				<img id='captchelfie_success' src='' style='display:none; top:0; left:0; width:64px; height:64px; position:absolute;z-index:555;'></img>
				<img id='captchelfie_share' src='' style='cursor:pointer;display:none; top:0; left:0; width:64px; height:64px; position:absolute;z-index:555;'></img>
				<img id='captchelfie_camera_help' src='' style='cursor:help; top:0; left:254px; width:64px; height:64px; position:absolute;z-index:555;'></img>
					<div id='captchelfie_share_body_wrap' style='display:none;position:absolute; top:0px; left:64px; border:0px dotted gray; width:205px;height:200px;overflow:hidden;z-index:600;'> 
					".	$this->get_share_body() ."
					</div>			
				<img id='captchelfie_share_loading' src='' style='cursor:pointer;display:none; top:32px; left:32px; width:64px; height:64px; position:absolute;z-index:556;'></img>
				<img id='captchelfie_shape' src='' style='top:0; left:0; width:100%;  position:absolute;'></img>
				<div id='captchelfie_snap_wrap' style='top:0; left:0; width:100%; position:absolute;'></div>
				<div id='captchelfie_powered' style='font-size: 50%;top:0; left:10; position:absolute;z-index:555;'><a target='apibio' href='http://api.bio'>powered by API.BIO</a></div>
				<input type='button' id='captchelfie_do' style='width: 100%' value=''></input> 
			</div>
			<div id='captchelfie_debug' style='display:none;'></div> 

			<div id='captchelfie_switch_wrap'>
				<input type='button' id='captchelfie_type' style='display:none; width:100%; margin: 5px 0px 5px 0px;' value=''></input> &nbsp;
			</div>

	</div>	
	<!-- API.BIO Captchelfie END --->	
		";
		return($ret);
	}
	
	function apibio_captchelfie_display()//we echo Captchelfie box here - standard WP forms
	{
		echo( $this->apibio_captchelfie_get());
	}

	function apibio_captchelfie_return_content($content)//we return content - custom forms
	{
//print_r("TROF\n<hr>\n$content\n<hr>\n");
//print_r(func_get_args());		
//		return $content ;
		return  $this->apibio_captchelfie_get() . $content ;
	}
	
	function apibio_captchelfie_get()//just generate text here, see above
	{
		$val = get_option('captchelfie'); 
		$apibio_key  = $val['apibio-key'];
		$apibio_api_url = $val['apibio-api-url'];
		$content = '';
		$content .= '<div class="apibio_form" >';
		if ( !$apibio_key || ! $apibio_api_url ) //not configured
		{
			if (current_user_can( 'manage_options' ) ) 
			{
//				$content .= $this->apibio_captchelfie_configure_msg();//we show qtip - must be enough
			}
		}
		if( (!is_user_logged_in()) || ($val['options-logged-too'] == '1') ) //only to strangers! 
		{
			$content .= $this->apibio_captchelfie_body();
		}
		$content .= '</div>'; 
		return $content;
	} //EOF apibio_captchelfie_display()

	function apibio_captchelfie_check()//we check if Captchelfie succeded
	{
		global $apibio_captchelfie_supported;
//print_r($_POST);//debug
//return new WP_Error( 'gglcptch_error', 'C '. print_r($_POST,true) ); //debug
//we check if Captchelfie succided,
		if( (isset($_POST['captchelfie'])) && ($_POST['captchelfie'] == 'PASSED')) //if ok, we switch off hooks from other captches
		{
//print_r($apibio_captchelfie_supported);				
			foreach($apibio_captchelfie_supported as $hook_system)
			{
				$check_filters = $hook_system['check_filters'];
				foreach($check_filters as $filter)
				{
					if(has_filter( $filter['tag'], $filter['hook'] ))
					{
						$res = remove_filter( $filter['tag'], $filter['hook'],$filter['priority']);
						if(!$res)
						{
//							echo('can not remove ' . $filter['tag']. ','. $filter['hook'] ."\n");//debug
						}
						else
						{
//							echo('removed ' . $filter['tag']. ','. $filter['hook']."\n"); //debug
						}
					}//there is such a filter
				}//filters for system
			}//hook systems
		}
		else//we let it go the usual way
		{
		}
	}


	function init() {
		load_plugin_textdomain( 'captchelfie', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
	
	function admin_menu() { 
		add_options_page( __( 'API.BIO Captchelfie', 'captchelfie' ), __( 'API.BIO Captchelfie', 'captchelfie' ), 'manage_options', 'captchelfie', array( $this, 'render_options' ) );
	}	
	
	function sanitize( $input ) { //do nothing for now
//print_r($input);die(' -Sanitize');	
		return $input;
	}
	
	function admin_init() {
//		load_plugin_textdomain( 'captchelfie', false, basename( dirname( __FILE__ ) ) . '/languages' );
		register_setting( 'captchelfie', 'captchelfie', array( $this, 'sanitize' ) );
		
		$preview_section_title = __( 'Preview', 'captchelfie' );
		add_settings_section( 'admin_preview', $preview_section_title, array( $this,'apibio_preview_out'), 'captchelfie' );//section callback!
		
		$keys_section_title = __('API.BIO Application','captchelfie').' ( <a href="https://api.bio/keys/" target=_blank>'.__('request at API.BIO','captchelfie').'</a> )';
		add_settings_section( 'app_keys', $keys_section_title, '', 'captchelfie' );		
		
		$key_title = __( 'Application Key', 'captchelfie' );
		add_settings_field( 'apibio-key', $key_title, array( $this, 'apibio_key_field_out' ), 'captchelfie', 'app_keys' );
		
		$api_url_title =  __( 'API URL', 'captchelfie' ); 
		add_settings_field( 'apibio-api-url',$api_url_title, array( $this, 'apibio_api_url_field_out' ), 'captchelfie', 'app_keys' );
		
		$forms_section_title = __( 'Served Forms', 'captchelfie' );
		add_settings_section( 'served-forms', $forms_section_title, '', 'captchelfie', 'served-forms' ); 
		
		$forms_login_form_title =  __( 'Login form', 'captchelfie' ); 
		add_settings_field( 'l-form',$forms_login_form_title, array( $this, 'apibio_l_form_out' ), 'captchelfie', 'served-forms' );
		
		$forms_registration_form_title =  __( 'Registration form', 'captchelfie' ); 
		add_settings_field( 'r-form',$forms_registration_form_title, array( $this, 'apibio_r_form_out' ), 'captchelfie', 'served-forms' );		

		$forms_reset_pwd_form_title =  __( 'Reset password form', 'captchelfie' ); 
		add_settings_field( 'rp-form',$forms_reset_pwd_form_title, array( $this, 'apibio_rp_form_out' ), 'captchelfie', 'served-forms' );
		
		$forms_comments_form_title =  __( 'Comments form', 'captchelfie' ); 
		add_settings_field( 'c-form',$forms_comments_form_title, array( $this, 'apibio_c_form_out' ), 'captchelfie', 'served-forms' );
		
		$forms_contact_form_title =  __( 'Contact form (3rd party)', 'captchelfie' ); //third parties  
		add_settings_field( 'cf-form',$forms_contact_form_title, array( $this, 'apibio_cf_form_out' ), 'captchelfie', 'served-forms' );		

		$options_section_title = __( 'Extra Options', 'captchelfie' ); ;
		add_settings_section( 'extra-options', $options_section_title, '', 'captchelfie', 'extra-options' ); 
		
		$options_mirrir =  __( 'Mirror mode', 'captchelfie' ); 
		add_settings_field( 'options-mirrir',$options_mirrir, array( $this, 'apibio_options_mirror' ), 'captchelfie', 'extra-options' );
		
		$options_logged_too =  __( 'Show to logged users', 'captchelfie' ); 
		add_settings_field( 'options-logged-too',$options_logged_too, array( $this, 'apibio_options_logged_too' ), 'captchelfie', 'extra-options' );
		
		$options_success_top =  __( 'Stop on success', 'captchelfie' ); 
		add_settings_field( 'options-success-stop',$options_success_top, array( $this, 'apibio_options_success_stop' ), 'captchelfie', 'extra-options' );
		
		$pic_section_title = __( 'Recent Captchelfies', 'captchelfie' ) . ' ( <a id="captchelfiers_refresh" href="/" >'.__('refresh','captchelfie').'</a> )<div class="lc_qth lc_visitors"></div>';
		add_settings_section( 'recent-pics', $pic_section_title, array( $this,'apibio_pics_out'), 'captchelfie' );//section callback!

		$pics_keep =  __( 'Keep Captchelfies for', 'captchelfie' ); 
		add_settings_field( 'recent-pics-keep',$pics_keep, array( $this, 'apibio_pics_keep' ), 'captchelfie', 'recent-pics' );
		
		
	}
	
	
	function apibio_preview_out() {
		echo($this->apibio_preview_create());
	}
	
	function apibio_preview_create() {
		$this->apibio_captchelfie_js_scripts();
		$ret = ""
				. "\n<!-- CAPTCHELFIE PREVIEW START -->\n"
				. "<div id='captchelfie-preview' >"		
				. "<div style='width:320px;padding: 0px; font-size:200%;border:0px dashed gray;text-align:center;'>"
				. "<img src='" . plugins_url( '', __FILE__ ). "/img/standard_captcha.png' style='cursor: not-allowed;' class='lc_standardcaptcha'></img>"
				. "</div></div>"
				. "<script>jQuery(window).ready(function(){captchelfie_aqh('.lc_standardcaptcha','".  __( 'In real form here is going to be the Standard Captcha you use (if any)', 'captchelfie' ) . "')});</script>"
				. "\n<!-- CAPTCHELFIE PREVIEW END -->\n";//stub to simulate standard
		$ret .= $this->apibio_captchelfie_body();
		return($ret);
	}


	function apibio_key_field_out() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[apibio-key]" type="text" style="max-width:350px;width:100%;"  value="<?php echo $val['apibio-key']; ?>" /><div class="lc_qth lc_apikey"></div><?php
	}
	
	function apibio_api_url_field_out() {
		$val = get_option('captchelfie'); //echo $val['apibio-api-url'];
		?><input name="captchelfie[apibio-api-url]" READONLY type="text" style="max-width:350px;width:100%;" value="<?php echo('https://ln.api.bio/fc/');?>" /><div class="lc_qth lc_apiurl"></div><?php
	}	
	
	function apibio_l_form_out() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[l-form]" type="checkbox"  <?php checked( 1, $val['l-form'] ) ?> value="1" /><div class="lc_qth lc_login"></div><?php
	}	
	
	function apibio_r_form_out() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[r-form]" type="checkbox"  <?php checked( 1, $val['r-form'] ) ?> value="1" /><div class="lc_qth lc_register"></div><?php
	}	
	
	function apibio_rp_form_out() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[rp-form]" type="checkbox"  <?php checked( 1, $val['rp-form'] ) ?> value="1" /><div class="lc_qth lc_password"></div><?php
	}

	function apibio_c_form_out() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[c-form]" type="checkbox" <?php checked( 1, $val['c-form'] ) ?> value="1" /><div class="lc_qth lc_comment"></div><?php
	}	
		
	function apibio_cf_form_out() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[cf-form]"  type="checkbox"  <?php checked( 1, $val['cf-form'] ) ?> value="1" /><div class="lc_qth lc_contact"></div><?php
	}		
	

	function apibio_options_mirror() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[options-mirror]"  type="checkbox"  <?php checked( 1, $val['options-mirror'] ) ?> value="1" /><div class="lc_qth lc_mirror"></div><?php
	}

	function apibio_options_logged_too() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[options-logged-too]"  type="checkbox"  <?php checked( 1, $val['options-logged-too'] ) ?> value="1" /><div class="lc_qth lc_logged_too"></div><?php
	}	
	
	function apibio_options_success_stop() {
		$val = get_option('captchelfie'); 
		?><input name="captchelfie[options-success-stop]"  type="checkbox"  <?php checked( 1, $val['options-success-stop'] ) ?> value="1" /><div class="lc_qth lc_success_stop"></div><?php
	}	
	
	function apibio_pics_out()
	{
		echo("<div id='captchelfie_visitors' style='max-width:540px;max-height:150px;overflow:auto;border:1px dashed gray;'></div>");
	}	
	
	function apibio_pics_keep() {
		$val = get_option('captchelfie'); 
		$cur_kept_seconds = 60 * 60 * 24; //one day, default
		if(isset($val['options-pics-keep']))
		{
			$cur_kept_seconds = $val['options-pics-keep'];
		}
		$dir_error_message = ''; //no error so fare
		if($cur_kept_seconds > 0)
		{
			$dir_error_message = $this->prepare_storage();		
			if(strlen($dir_error_message) !== 0)//ooops! - got error
			{
				$cur_kept_seconds = 0;
				$upload = wp_upload_dir();
				$upload_base_dir = $upload['basedir'];
				$dir_error_message = "<br>" . __("ERROR! Please make sure the directory ") . "<br><b>". $upload_base_dir . "</b><br>" .  __("exists and is not write-protected.") . "<br>(" .$dir_error_message. ")" ;
				$dir_error_message .= "<script>jQuery('#select-options-pics-keep').css('color','red');</script>";
			}		
		}
		$select_text = '<select id="select-options-pics-keep" name="captchelfie[options-pics-keep]">';
		$select_text .= '<option value="0">'. __('Zero seconds (Sharing will be disabled)', 'captchelfie') .'</option>';
		$select_text .= '<option value="' .  (60 * 60 * 24) . '">'. __('One Day', 'captchelfie') .'</option>';		
		$select_text .= '<option value="' .  (60 * 60 * 24 * 2 ) . '">'. __('Two Days', 'captchelfie') .'</option>';	
		$select_text .= '<option value="' .  (60 * 60 * 24 * 3 ) . '">'. __('Tree Days', 'captchelfie') .'</option>';	
		$select_text .= '<option value="' .  (60 * 60 * 24 * 7 ) . '">'. __('One Week', 'captchelfie') .'</option>';	
		$select_text .= '<option value="' .  (60 * 60 * 24 * 14 ) . '">'. __('Two Weeks', 'captchelfie') .'</option>';			
		$select_text .= '<option value="' .  (60 * 60 * 24 * 30 ) . '">'. __('One Month', 'captchelfie') .'</option>';				
		$select_text .= '</select>';
		$select_text .= '<div class="lc_qth lc_pics_keep"></div>';
		$select_text .= "<script>jQuery('#select-options-pics-keep option[value=\"".$cur_kept_seconds."\"]').attr('selected', 'selected');</script>";//lazy way =)
		echo($select_text);
		echo($dir_error_message);
	}
	
	function render_options() {
//print_r($_POST); //debug		
		?>
		<!-- Styles for the admin help subsystem -->
		<style>
		.lc_qth{
			display:inline-block;
			width:24px;
			height:24px;
			background-image: url("<?php echo(plugins_url( '', __FILE__ )); ?>/img/help.png");
		}
		.captcelfier_wrap{
			display:inline-block;
			padding:1px;
			margin:2px;
			width:100px;
			border: 1px solid gray;
			text-align:center;
		}
		</style>
		<div class="wrap">
	        <h2><?php _e( 'API.BIO Captchelfie', 'captchelfie' ); ?></h2>
	        <form action="options.php" method="POST">
	            <?php settings_fields( 'captchelfie' ); ?>
	            <?php do_settings_sections( 'captchelfie' ); ?>
	            <?php submit_button(); ?>
	        </form>
	    </div>
		
		<!-- Scripts for the admin help subsystem -->		
		<script>

		jQuery(window).ready(function(){ 
			jQuery('h2:contains("<?php echo( __( 'Preview', 'captchelfie' )); ?>")').append(" <div class='lc_qth lc_preview'></div>");


			captchelfie_aqh('.lc_preview','<?php echo( __( 'Preview shows how the Captchelfie will appear on the pages', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_mirror','<?php echo( __( 'If set, camera image is going to be flipped horisontaly, camera works like a miror', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_logged_too', '<?php echo( __( 'If set, Captchelfie appears for logged users too - good for testing your pages', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_success_stop','<?php echo( __( 'If set, user can not play more after successfully passing the Captchelfie', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_login','<?php echo( __( 'If set, WP Login Form will be served', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_register','<?php echo( __( 'If set, WP Register Form will be served', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_contact','<?php echo( __( 'If set, WBS Contact Form will be served', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_comment','<?php echo( __( 'If set, WP Comment Form will be served', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_password','<?php echo( __( 'If set, WP Reset Password Form will be served', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_apikey','<?php echo( __( 'The Application Key. this field may not be empty!', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_apiurl','<?php echo( __( 'Application Program Interface URL to call', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_visitors','<?php echo( __( 'Recent Captchelfies taken by your visitors. Visitors suppose to expect taken picture is going to be stored', 'captchelfie' )); ?>');
			captchelfie_aqh('.lc_pics_keep','<?php echo( __( 'Time period for how long the Recent Captchelfies are going to be kept on the server. Old ones are erased when Captchelfie is taken.', 'captchelfie' )); ?>');

		});
		
		function captchelfie_get_visitors(start_from) 
		{
			jQuery('#captchelfiers_refresh').hide();
			if(start_from == 0)
			{
				jQuery('#captchelfie_visitors').html('');
			}
			jQuery('#captchelfie_visitors').html(jQuery('#captchelfie_visitors').html()+'<img class="captchelfie_visitors_more" src="'+captchelfie_plugin_url+'/img/ajax_loader.gif"></img>');
			var ajax_url = captchelfie_site_url + '/wp-admin/admin-ajax.php'; //get_site_url()
//			console.log('About to ajax to ' + ajax_url);
			jQuery.ajax({
				url: ajax_url, // this is the object instantiated in wp_localize_script function
				type: 'POST',
				async:true,//well, default
				data:{
					action: 'captchelfie_unique_action', // this is the function in your functions.php that will be triggered
					operation: 'adminlist',
					startfrom: start_from,
				},
				success: function( data ){
//					console.log('adminlist ajax success'); console.log( data );
					jQuery('.captchelfie_visitors_more').remove();
					var current_html = jQuery('#captchelfie_visitors').html();
					jQuery('#captchelfie_visitors').html(current_html +  data); //current_html +  data
					jQuery("a.captchelfier").on('click',function(event){
						event.preventDefault();	
						var img_url = jQuery(this).parent().attr('share_tag');
						jQuery(this).parent().html("<img class='captchelfie_thumb' onClick='window.open(\""+img_url+"\",\""+img_url+"\");' src='" + img_url + "'></img><br>" + jQuery(this).html() );
						jQuery(this).css('display','none');
						
					})
				},
				error: function(e){
					console.error('ajax error'); console.error(e);
					jQuery('#captchelfie_visitors').html("AJAX ERROR on " + ajax_url);
				},
			}).always(function() { 
					jQuery('#captchelfiers_refresh').show();
				});		 
		}
		jQuery('#captchelfiers_refresh').click(function(event){
			event.preventDefault();	
			captchelfie_get_visitors(0);
		})
		
		captchelfie_get_visitors(0);
		</script>
		<?php
	}
	
	
	
	function prepare_storage() //prepaer storage for pictures. 
	{
		$ret = ''; //we return error message
		$captchelfie_rel_path = '/facedetect';
		$upload = wp_upload_dir();
		$upload_base_dir = $upload['basedir'];
		$upload_base_url = $upload['baseurl'];
		$storage_dir = $upload_base_dir . $captchelfie_rel_path;
		if (!is_dir($storage_dir)) 
		{
			$oldmask = umask(0);  // helpful when used in linux server  
			if(!@mkdir( $storage_dir, 0711  )) //set 755 to view files
			{
				$err = error_get_last();
				$ret = $err['message'];
				return($ret); 				
			}
		}
/*		we do not write index there
		$index_txt = '<'."?php\n include_once('".dirname( __FILE__ )."/img.php');\ncaptchelfie_image_out(dirname( __FILE__ ));" ; //image.php content
		$index_filename = $storage_dir . '/' . 'index.php';
		if(! @file_put_contents(index_filename,$index_txt )) //we save index every time - just to cover updates in next versions.
		{
			$err = error_get_last();
			$ret = $err['message'];
		}
*/		
		return($ret);//if no error must be empty
	}//prepare_storage()
	
	
	function doAjax()//whaaa?! Mommy, it's camelback! =)
	{
//print_r($_POST); die; //yep, you've guessed right - it's debug
		$ret = ''; //whatever we return here goes to ajax caller
		$cts = '{CAPTCHELFIE_SPARATOR}';
		$extra_msg = '';
		$captchelfie_rel_path =  DIRECTORY_SEPARATOR . 'facedetect';
		$upload = wp_upload_dir();
		$upload_base_dir = $upload['basedir'];
		$upload_base_url = $upload['baseurl'];
		$storage_dir = $upload_base_dir . $captchelfie_rel_path;
		$storege_base_url = $upload_base_url . $captchelfie_rel_path;
//die($storage_dir . ' ' . $storege_base_url);
//we store picture from the camera return short_filename{CAPTCHELFIE_SPARATOR}share_tag{CAPTCHELFIE_SPARATOR}share_url{CAPTCHELFIE_SPARATOR}share_image{CAPTCHELFIE_SPARATOR}share_title{CAPTCHELFIE_SPARATOR}share_desc
//or message on error
		if($_POST['operation'] == 'store') 
		{
			$caller_ip =  $_SERVER['REMOTE_ADDR']?:($_SERVER['HTTP_X_FORWARDED_FOR']?:$_SERVER['HTTP_CLIENT_IP']);
			$current_time = ''.time();  //seconds from 1 Jan 1970
			//here we going to remove old files. we list it in the admin anyway, but gotta do it here, to free space on new upload
			$val = get_option('captchelfie'); 
			$cur_kept_seconds = 60 * 60 * 24; //one day, default
			if(isset($val['options-pics-keep']))
			{
				$cur_kept_seconds = $val['options-pics-keep'];
			}
			$all_files = scandir($storage_dir,1);
			foreach($all_files as $cur_filename)
			{
				if(substr($cur_filename, -4) == '.jpg') //we process only image files
				{
					$captchelfie_attrs = explode('_',$cur_filename);
					$captchelfie_seconds = $captchelfie_attrs[0];
					if( ($current_time - $cur_kept_seconds) > $captchelfie_seconds)
					{
						$full_cur_filename = $storage_dir . DIRECTORY_SEPARATOR . $cur_filename;
						unlink($full_cur_filename); //delete
					}
				}//.jpg
			}//foreach

			//we done with removing old files
			$splited = explode(',', substr( $_POST['img'] , 5 ) , 2);
			$mime=$splited[0];
			$data=$splited[1];
			$filename =  $current_time . '_' . $caller_ip . '_' . '.jpg'; //so we can sort it by name, IP makes it unique
			$filename_to_remove = $_POST['old'];
			if($filename_to_remove !== '')
			{
				$full_filename_to_remove =  $storage_dir . DIRECTORY_SEPARATOR . $filename_to_remove;
				unlink($full_filename_to_remove);//blindly delete it
			}
			$share_tag = sha1($current_time . $caller_ip);//unique 
			$jpg_data = base64_decode($data) ;
			$full_filename = $storage_dir . DIRECTORY_SEPARATOR . $filename;
			if(file_put_contents( $full_filename,$jpg_data )) //we ok
			{
//				$share_image = $upload_base_url . $captchelfie_rel_path . '?captchelfie=' . $share_tag; //return always
				$share_image = plugin_dir_url( __FILE__ ) . "img.php" . '?captchelfie=' . $share_tag; //return always
				$share_title = '';
				$share_desc = '';
				$share_url = $_SERVER['HTTP_REFERER']; 
				if( (strpos($share_url,'wp-login.php') !== FALSE) || (strpos($share_url,'wp-admin') !== FALSE))//for login pages we are going to return home page
				{
//					$share_url = add_query_arg( 'captchelfie', $share_tag, get_site_url() );//main URL. not good to share one URL from another (for FB for example)
					$share_url = add_query_arg( 'captchelfie', $share_tag,$share_url);//we still share current, but going to do soft jump from there
					$share_title =  get_bloginfo('name');
					$share_desc = get_bloginfo('description');
				}
				else //we going to shage current page, do not overwrite title and description 
				{
					$share_url = add_query_arg( 'captchelfie', $share_tag,$share_url);
				}
				$ret = $filename . $cts . $share_tag . $cts . $share_url . $cts . $share_image. $cts . $share_title . $cts . $share_desc;
				exit($ret); 
			}
			else //oh, got error
			{
				$ret = 'ERROR ' . $full_filename . ' ' .  print_r(error_get_last(),true) .$extra_msg;
				exit($ret); 
			}
		}
		if($_POST['operation'] == 'adminlist') //we return list of files in the directory
		{
			if ( ! is_admin() )
			{
				exit('auth failed'); 
			}
			$list_len = 1000; //that's how many we return
			$start_from = $_POST['startfrom'];
			$all_files = scandir($storage_dir,1);
			$count = 0;
			$ret = '';
			foreach($all_files as $cur_filename)
			{
				if(substr($cur_filename, -4) == '.jpg') //we process only image files
				{
					$count++;
					$full_cur_filename = $storage_dir . DIRECTORY_SEPARATOR . $cur_filename;
					if($count > $list_len)//for now we just keep $list_len image files
					{
						unlink($full_cur_filename); //delete
						continue;
					}
					if($count > $start_from)
					{
						$captchelfie_attrs = explode('_',$cur_filename);
						$captchelfie_seconds = $captchelfie_attrs[0];
						$captchelfie_date = date('H:i:s\<\b\r\>d.m.Y',$captchelfie_seconds);
						$captchelfie_ip = $captchelfie_attrs[1];
						$current_hash = sha1($captchelfie_seconds . $captchelfie_ip);//unique 
						$view_img_url = plugin_dir_url( __FILE__ ) . "img.php" . '?captchelfie=' . $current_hash; //return always			
						$ret .= "<div share_tag='$view_img_url' title='$cur_filename' class='captcelfier_wrap'><a href='#' class='captchelfier'>$captchelfie_date<br>$captchelfie_ip</a></div>";
					}
				}
			}
			exit($ret);
		}
		return($ret);
	}
	

}//end of class apibio_captchelfie

$apibio_captchelfie = new apibio_captchelfie();



//END OF FILE
