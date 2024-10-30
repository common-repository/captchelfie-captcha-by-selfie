
(function( captchelfie_code_block, $, undefined ) { //namespace - begin
	
	var c_screen_block_width = 320; //constant. this size(h) pic we show on screen. CONFIG HERE	
	var c_main_width = 640; //constant. this size(w) pic we sending in
	var c_main_height = 480; //constant. this size(h) pic we sending in
	var main_width = c_screen_block_width;//start with that
	var main_height = Math.round((main_width / 4) * 3);	//for the Flash must be  4x3,
	console.log("Start operation in " + main_width + " x " + main_height);
	var captchelfie_passed = false; //global
	var original_captcha_block = false; //global
	var current_stored_name = '';
	var current_share_tag = '';
	var share_url = ''; 	
	var share_image = ''; 
	var share_title = '';
	var share_desc = '';
	var cur_timer = null;
	var pluso_loaded = false;
	

	
	function _t(debug_message)
	{
		if(jQuery('#captchelfie_debug').css('display') == 'none')
		{
//			return;
		}
		jQuery('#captchelfie_debug').html(jQuery('#captchelfie_debug').html() + '<hr>' + debug_message);
	}
	
	jQuery(window).ready(function(){ 
		CameraHelp('#captchelfie_camera_help');
		console.log("Window is ready");
		window.addEventListener("orientationchange", function() {
			_t(window.orientation);//0,90,-90
			//TODO - we have to resize camera block here
		}, false);
		
//jQuery('#captchelfie_block_wrap').closest("form").css('border','10px dotted yellow');	//debug		 			
		if(jQuery('#captchelfie_block_wrap').closest("form").length == 0)//just checking MUST NEVER HAPPEN!!
		{
			alert("WE ARE NOT INSIDE THE FORM!? \n Plugin is not operational!");
			return; //we are off the comission
		}
		
//jQuery('#captchelfie_block_wrap').closest("form").find('input[type=submit]').css('border','10px dotted yellow');	//debug		 	
		jQuery("#captchelfie_shape").attr('src',captchelfie_plugin_url + "/img/shape.png");
		jQuery("#captchelfie_success").attr('src',captchelfie_plugin_url + "/img/success.png");
		jQuery("#captchelfie_share").attr('src',captchelfie_plugin_url + "/img/share.png");
		jQuery("#captchelfie_loading").attr('src',captchelfie_plugin_url + "/img/ajax_loader.gif");
		jQuery("#captchelfie_share_loading").attr('src',captchelfie_plugin_url + "/img/ajax_loader.gif");
		jQuery("#captchelfie_camera_help").attr('src',captchelfie_plugin_url + "/img/camera_help.png");
		
	
		
		original_captcha_block = captchelfie_insert();
		console.log("Standard Captcha: " + original_captcha_block); 
				//setting "sight" shape. Note, same figure is built into .swf file, the original one is stored as 'webcam_original.swf'

		jQuery('#captchelfie_code_holder').css('width',main_width+'px').fadeIn(100);
		if(original_captcha_block !== false)//there is another captcha
		{
			console.log('Found standard captcha block: ' + original_captcha_block);
			if(get_cookie('captchelfie_type') == 'standard') //set and user prefers standard
			{
				jQuery('#captchelfie_block_wrap').hide(500,function(){ //at least 2000! google re-captcha v1 - expands on timer. gotta do long enough
					jQuery('#captchelfie_type').val(captchelfie_l_live).stop().fadeIn().prop( "disabled", false );
				});				
			}
			else //not set, or user prefers capthelfie
			{
				captchelfie_disable_submit(true);//we disable submit
				var hide_delay = 500; //at least 2000! google re-captcha v1 - expands on timer. gotta do long enough
				if(jQuery('.lc_standardcaptcha').length > 0)//captcha simulator
				{
					hide_delay = 10; //cheating - remove captcha simulator fast - more convenient in admin area
				}
				
				jQuery(original_captcha_block).css('display','none');
				jQuery(original_captcha_block).css({ opacity: 0.0 }).hide(hide_delay,function(){ 
					jQuery(original_captcha_block).css({ opacity: 1.0 }).attr('style','display: none!important;');
					jQuery(original_captcha_block).find('input[type=text]').val('0'); //we do it to prevent 'submit stop' from smart standard captchas - value set	
					jQuery('#captchelfie_type').val(captchelfie_l_standard).stop().fadeIn().prop( "disabled", false );

					var count = 5000;
					cur_timer = setInterval(function(){
						count=count-10; if(count < 0) {clearInterval(cur_timer);}
						//console.log('DAMN reCAPTCHA! ' + count + ' ' + original_captcha_block + " " + jQuery(original_captcha_block).css('display'));
						jQuery(original_captcha_block).css('display','none');
					},20);

				});
			}
		}
		else //we operate alone
		{
			jQuery('#captchelfie_type').css('display','none');
			captchelfie_disable_submit(true);//we disable submit
		}
		
		jQuery("#captchelfie_snap_wrap").hide(100);
		jQuery('#captchelfie_do').val(captchelfie_l_reset).prop( "disabled", false );
			
		adjust_elements_size(main_width,main_height,c_main_width,c_main_height);			
		adjust_login_forms(main_width + 10);
		console.log("Initial show is done");
		
		//click on main process button
		jQuery("#captchelfie_do").on('click',function(event){
			event.preventDefault();	
		
			if(jQuery('#captchelfie_do').val() == captchelfie_l_reset)
			{
				console.log('reset');
				jQuery('#captchelfie_share').stop().css('display','none');
				if(captchelfie_passed)
				{
					jQuery('#captchelfie_success').stop().show(500);
//					jQuery('#captchelfie_share').stop().hide(200);				
				}
				captchelfie_camera_init()
				jQuery("#captchelfie_snap_wrap").hide(200);
				return;
			}

			if(jQuery('#captchelfie_do').val() == captchelfie_l_detect_face)
			{
				if((captchelfie_api_url == '') || (captchelfie_api_key == ''))
				{
					show_auth_error();
				}
				else
				{
					console.log('detect');
					captchelfie_detect_face();
				}
				return;
			}
		});	//captchelfie_do
	
		//click on switch captcha type button
		jQuery("#captchelfie_type").on('click',function(event){
			event.preventDefault();	
			var current_text = jQuery('#captchelfie_type').val() 
			if(current_text.indexOf(captchelfie_l_live) != -1) //now Captchefie
			{
				jQuery('#captchelfie_type').val(captchelfie_l_standard);
				set_cookie('captchelfie_type','captchelfie',100);
				console.log('Selected captcha type: ' + get_cookie('captchelfie_type'));
				jQuery(original_captcha_block).slideUp(500,function(){
					jQuery(original_captcha_block).attr('style','display: none!important;');
					jQuery(original_captcha_block).find('input[type=text]').val('0');
					});
				jQuery('#captchelfie_block_wrap').slideDown(500);
				if(captchelfie_passed == false)
				{
					captchelfie_disable_submit(true);//we disable submit						
				}
			}
			else //now standard captcha
			{
				clearInterval(cur_timer);
				jQuery(original_captcha_block).stop();
				jQuery('#captchelfie_type').val(captchelfie_l_live);
				set_cookie('captchelfie_type','standard',100);
				console.log('Selected captcha type: ' + get_cookie('captchelfie_type'));
				jQuery(original_captcha_block).slideDown(500);
				jQuery(original_captcha_block).find('input[type=text]').val('');
				jQuery('#captchelfie_block_wrap').slideUp(500);	
				captchelfie_disable_submit(false)//we enable submit - other captcha in place			
			}
		});	//captchelfie_type
		
		jQuery('#captchelfie_block_wrap').closest("form").submit(function( event ) {
//we do nothing now			
//alert("Submit");
		});
		
	});//DOM ready
	
	
	function prepare_sharing()
	{
		jQuery("#captchelfie_message").stop().css('display','block').css('color','yellow').html(captchelfie_l_share_ready).fadeOut(1500);
		jQuery('#captchelfie_success').stop().hide(500);
		jQuery('#captchelfie_share').attr('title',captchelfie_l_detected_share).show(500);

	}//prepare_sharing() 


	jQuery("#captchelfie_share").bind('click',function(event){
		event.preventDefault(); 
		jQuery('#captchelfie_do').prop( "disabled", true ).slideUp();//hide

		jQuery('#captchelfie_share_body_wrap div.pluso').attr('data-image',share_image);//always
		console.log('Share Image URL: ' + share_image);
		jQuery('#captchelfie_share_body_wrap div.pluso').attr('data-url',share_url); //always
		console.log('Share URL: ' + share_url)		
		if(share_title != '')
		{
			jQuery('#captchelfie_share_body_wrap div.pluso').attr('data-title',share_title); //always
			console.log('Share title: ' + share_title)						
		}
		if(share_desc != '')
		{
			jQuery('#captchelfie_share_body_wrap div.pluso').attr('data-description',share_desc); //always
			console.log('Share description: ' + share_desc)			  			
		}

		if(!pluso_loaded)
		{
			(function() {
				if (window.pluso)if (typeof window.pluso.start == 'function') return;
				if (window.ifpluso==undefined) { window.ifpluso = 1;
				var d = document, s = d.createElement('script'), g = 'getElementsByTagName';
				s.type = 'text/javascript'; s.charset='UTF-8'; s.async = true;
				s.src = ('https:' == window.location.protocol ? 'https' : 'http')  + '://share.pluso.ru/pluso-like.js';
				var h=d[g]('body')[0];
				h.appendChild(s);
			}})();		
			pluso_loaded = true;
			var pluso_text = jQuery('#captchelfie_share_body_wrap').html(); 
			console.log('Pluso loaded: ' + pluso_text)	
		}//pluso_loaded == false
		jQuery('#captchelfie_share_body_wrap').toggle(1000);
	})//on click

	
	function ajax_save_pic(img_data)//on submit and on share
	{
		jQuery('#captchelfie_share_loading').css('display','block');
		jQuery.ajax({
			url: captchelfie_site_url + '/wp-admin/admin-ajax.php', // this is the object instantiated in wp_localize_script function
			type: 'POST',
			data:{
				action: 'captchelfie_unique_action', // this is the function in your functions.php that will be triggered
				operation: 'store',
				img: img_data,
				old: current_stored_name,
			},
			success: function( data ){
				console.log( data ); //debug only - plenty of data
				var a = data.split('{CAPTCHELFIE_SPARATOR}'); 
				if(a.length == 6)
				{
					current_stored_name = a[0];
					current_share_tag = a[1];
					share_url = a[2];	
					share_image = a[3];
					share_title = a[4];
					share_desc = a[5];
					if( (captchelfie_allow_share) && (jQuery('#captchelfie_do').val() != captchelfie_l_detect_face) )//we can store and user not taking another selfie
					{
						prepare_sharing();//only on right responce (meaning file was saved)
					}
				}	
				else
				{
						console.error('Unexpeced responce from ajax:');
						console.log( data );
				}

			},
			error: function( e ){
				console.error( e );
			},
			complete: function(xhr,status){
				jQuery('#captchelfie_share_loading').css('display','none');
			},
			
		});		
	}
	
	function adjust_elements_size(new_width,new_hight,camera_width,camera_height) //just readability
	{
		_t("adjust_elements_size: " + new_width+','+new_hight+','+camera_width+','+camera_height);
		jQuery('#captchelfie_shape').css('height',new_hight+'px').css('width',(new_hight * 4 / 3 )+'px').css('left', (new_width - (new_hight * 4 / 3 )) /2 + 'px');
		jQuery('#captchelfie_camera').css('height',new_hight+'px').css('width',new_width+'px').css('border','0px solid magenta');
		jQuery('#captchelfie_snap_wrap').css('height',new_hight+'px');
		jQuery('#captchelfie_block_wrap').css('border','1px dotted grey');
	}
	
	function adjust_camera_scales(new_width,new_hight,camera_width,camera_height) //just readability
	{
		var jquery_video = jQuery('#captchelfie_camera').find('video'); //just for readability
		_t("adjust_camera_scales: " + main_width+','+new_hight+','+camera_width+','+camera_height);
		jquery_video.css('max-width',camera_width+'px');//to fix some stupid WP Themes -wierd in FF
		jquery_video.css('max-height',camera_height+'px');//to fix some stupid WP Themes -wierd in FF
		jquery_video.css('width',camera_width+'px').css('height',camera_height+'px');
		var adjusted_camera_width = jquery_video.width();
		var adjusted_camera_height = jquery_video.height();
		_t("Adjusted to the real camera sizes: " + adjusted_camera_width + ' x ' + adjusted_camera_height );
		var scale_x = new_width / camera_width;
		var scale_y = new_hight / camera_height;
		var new_transform = 'scaleX('+scale_x+') scaleY('+scale_y+')';
		_t("Adjusting to the real camera proportions: " + new_transform);
		jquery_video.css('transform',new_transform).css('border','0px solid red'); // Gotta be universal, bellow -  just in case, webcam.js does it 
		jquery_video.css('-webkit-transform',new_transform);// Chrome 
		jquery_video.css('-moz-transform',new_transform); //FF
		jquery_video.find('video').css('-ms-transform',new_transform); //MS
		jquery_video.find('video').css('-o-transform',new_transform); //Opera
	}
	
	function adjust_login_forms(new_width) //just readability
	{
		jQuery('#loginform').attr('style','min-width: '+new_width+'px !important;');
		jQuery('#lostpasswordform').attr('style','min-width: '+new_width+'px !important;');
		jQuery('#registerform').attr('style','min-width: '+new_width+'px !important;');	
	}
	
	function captchelfie_insert(){
		for(var i = 0; i < captchelfie_suppored_blocks.length; i++)
		{		
			var cur = jQuery(captchelfie_suppored_blocks[i]);
//console.log(captchelfie_suppored_blocks[i] + ' ' + cur.length);			
			if(cur.length > 0) //found
			{
//jQuery(captchelfie_suppored_blocks[i]).css('border','10px dotted yellow');	//debug		 	
//jQuery(captchelfie_suppored_blocks[i]).parent().css('border','10px dotted red');	//debug		
				var t = jQuery(captchelfie_code_holder).detach();
				t.insertBefore(jQuery(captchelfie_suppored_blocks[i]));
				return captchelfie_suppored_blocks[i]; //
			}
		}
		return false; //
	}

	
	function captchelfie_camera_init() {
//		console.log('Init:');console.log(Webcam);
		if(Webcam.live)
		{
			jQuery("#captchelfie_shape").fadeIn();//show it back (hidden during captchelfie_camera_init)
			jQuery('#captchelfie_do').val(captchelfie_l_detect_face);
			return true;//no double init!
		}
		jQuery("#captchelfie_powered").hide();
		jQuery("#captchelfie_camera_help").hide();
		jQuery("#captchelfie_shape").fadeOut();//we hide it to allow click on flash if needed
		jQuery("#captchelfie_message").stop().css('display','block').css('color','green').html(captchelfie_l_init).fadeOut(1500);
		//mirror mode from plugin configuration
		var mirror_mode = false;
		if(captchelfie_mirror_mode == '1')
		{
			mirror_mode = true;
		}
		
		//force flash. we have to check for Chrome and https
		var enforce_flash = false; //we do not force flash by default - HTML5 is better
		var raw = navigator.userAgent.match(/Chrom(e|ium)\/([0-9]+)\./);
		var chrome_ver = (raw ? parseInt(raw[2], 10) : false);
		if( 	(chrome_ver !== false) //is Chrome
			&& 	(chrome_ver > 46) //requires 'https'
			&& 	(window.location.href.indexOf('https') !== 0)//does not starts with httpS
		   )
		{
			console.log("Chrome over HTTP detected, forcing Flash");//debug
			enforce_flash = true;
		}
//enforce_flash = true; //debug
//console.log(Webcam);//debug
		console.log('Setting Camera to: ' + main_width +  ' x ' + main_height + ' , ' + c_main_width +  ' x ' + c_main_height);
		Webcam.set({
			width: main_width, //set above - you may change it
			height: main_height, //calculated by default to 3x4 of width
			dest_width: c_main_width, //constant above, we send bigger image than show
			dest_height: c_main_height,	// same 		
			image_format: 'jpeg',
			jpeg_quality: 90, //enough, affects image size big time!
			flip_horiz: mirror_mode,
			force_flash:enforce_flash,
			});	
		Webcam.attach( '#captchelfie_camera' );	
//console.log('Webcam Attached'); console.log(Webcam);//debug		
		if(  (jQuery('video').length > 0) ) //HTML5
		{
			console.log("Using HTML5 Video");
			try
			{
				Webcam.video.addEventListener('playing',function(){
					adjust_camera_block();
                },false);	//addEventListener
			}
			catch(err)
			{
				console.log('Can not adjust camera size:');console.log(err);		
			}		
		}
		else //we use Flash 
		{
			console.log("Using Flash Video");
			jQuery('#webcam_movie_embed').css('margin','0px');//fix for margin in some wp themes  
		}
//console.log(Webcam);//debug
		
		
		Webcam.on( 'live', function() {
//console.log('Webcam Live:');console.log(Webcam);	//debug
	
			jQuery("#captchelfie_shape").fadeIn();//show it back 
			jQuery('#captchelfie_do').val(captchelfie_l_detect_face);
		} );
		Webcam.on( 'error', function(err) {
			console.log('Webcam Error:');console.log(err);	//debug
			jQuery("#captchelfie_message").stop().css('display','block').css('color','red').html(err).fadeOut(1500);
		} );		
		
		return false; //we done
	}
	
	function adjust_camera_block()
	{
		var videoWidth = Webcam.video.videoWidth;
        var videoHeight = Webcam.video.videoHeight;
		console.log("Current: " + main_width + " x " + main_height);
		_t("Real video sizes:" + videoWidth + " x " + videoHeight);
		main_width = c_screen_block_width;
		main_height = Math.floor( c_screen_block_width * (videoHeight / videoWidth) ); //regular way			
//main_height = Math.floor( c_screen_block_width * (4 / 3) ); //regular 4/3 - testing only		
		console.log("Adjusted to " + main_width + ' x ' + main_height);
		adjust_elements_size(main_width, main_height,videoWidth,videoHeight);					
		adjust_camera_scales(main_width, main_height,videoWidth,videoHeight);
		Webcam.params.dest_width = videoWidth; //we gotta tell Webcam the dimmentions of the image we expect
		Webcam.params.dest_height = videoHeight; // -//-


//console.log(Webcam);
	}

	function show_auth_error(error_message)
	{
		jQuery('#captchelfie_camera').qtip({
			content: {
				title: error_message,
				text: captchelfie_auth_error,
				button: true,
			},
			show: {
				event: false, // Don't specify a show event
				ready: true, // Show the tooltip when ready
			},
			position: {
				my: 'top left',  // Position my top left...
				at: 'top left', // at the bottom right of... 
			},	
			hide: {
				event: false,
			},								
		});		
	}
	
	function captchelfie_detect_face() {	 	

		Webcam.snap( function(data_uri) {
			jQuery("#captchelfie_do").prop( "disabled", true );
			jQuery("#captchelfie_snap_wrap").html('<img style="width:'+main_width+'px;height:'+main_height+'px;" src="'+data_uri+'"/>');	
			_t("Snap Image natural: " + jQuery("#captchelfie_snap_wrap img").prop("naturalWidth") + " x " + jQuery("#captchelfie_snap_wrap img").prop("naturalHeight") );
			_t("Snap Image shown: " + jQuery("#captchelfie_snap_wrap img").width() + " x " + jQuery("#captchelfie_snap_wrap img").height() );
			_t('<img  src="'+data_uri+'"/>')
			jQuery("#captchelfie_snap_wrap").show(200);
			var client = new FaceApiClient(captchelfie_api_key);//do not show api_secret here or anywhere in javascript, use domain!
			client.setServer(captchelfie_api_url);
			var options = new Object();
			options.detect_all_feature_points = true;
			options.attributes = 'all';
			jQuery("#captchelfie_loading").css('display','block');
			try
			{
				console.log('Contacting ' + client.getServer());
				client.facesDetect(data_uri, null, options, function(data){ 
					jQuery("#captchelfie_loading").css('display','none');
					jQuery("#captchelfie_message").css('display','block');
// console.log(data); 
					var json_is_ok = true;
					try
					{
						data = JSON.parse(data);
					}
					catch(error)
					{
						console.log('JSON Error');console.log(error);	
						json_is_ok = false;
					}
					if(data.error_message)
					{
						console.log('Face Error');
						jQuery("#captchelfie_message").stop().css('display','block').css('color','red').html(data.error_message).fadeOut(1500);
						jQuery("#captchelfie_snap_wrap").hide(200).html('');
						if(data.error_message == 'AUTHORIZATION_ERROR')
						{
							show_auth_error(data.error_message);
						}
					}
					else //no error
					{
						var face_detected = ( (json_is_ok) && (data.photos) && (data.photos[0].tags) && (data.photos[0].tags.length > 0) );
						if(face_detected)
						{
							console.log('Face detected');	
							if(captchelfie_allow_share)
							{
								ajax_save_pic(data_uri);
							}
							if(captchelfie_passed == false) //once. passes is passed!
							{
								captchelfie_passed = true; 
								jQuery('#captchelfie_block_wrap').closest("form").prepend('<input type="hidden" name="captchelfie" value="PASSED"/>');
								captchelfie_disable_submit(false);//we enable submit - captcha passed		
								jQuery('#captchelfie_success').attr('title',captchelfie_l_detected_success).show();
								jQuery('#captchelfie_type').prop( "disabled", true ).stop().hide();//no need to switch to standard any more - passed
								if(captchelfie_stop_on_success == '1') //admin did not allow the users to play
								{
									jQuery('#captchelfie_do').slideUp();//hide
									Webcam.reset() //we switch camera off - no need to keep it
								}
							}
							jQuery("#captchelfie_message").stop().html(captchelfie_l_detected_ok).css('display','block').css('color','lightgreen').fadeOut(2000);
							data.photos[0].url = data_uri;
							var face_facts = processFaces(jQuery("#captchelfie_snap_wrap"), data.photos[0], true);
							jQuery('.captchelfie_api_face_frame').qtip({
								content: face_facts,
							});
							
							jQuery('.captchelfie_api_face_frame').on("click",function (e) { //click throught the face frame, even if it's over the button
								console.log('click through face frame');
								jQuery('.captchelfie_api_face_frame').css('display','none');
								jQuery(document.elementFromPoint(e.clientX, e.clientY)).trigger("click");
								jQuery('.captchelfie_api_face_frame').css('display','block');
							});	
							
							jQuery('#captchelfie_do').val(captchelfie_l_reset);//let them play again
						}
						else //not detected
						{
							console.log('Face NOT DETECTED');
//console.log(data); console.log(JSON.stringify(data));	//debug						
							jQuery("#captchelfie_message").stop().css('display','block').css('color','red').html(captchelfie_l_detected_error).fadeOut(1500);
							jQuery("#captchelfie_snap_wrap").hide(200).html('');			
					}
					}
					jQuery("#captchelfie_do").prop( "disabled", false );
				});
			}
			catch(error)
			{
				console.log(error);
				jQuery("#captchelfie_loading").css('display','none');
				jQuery("#captchelfie_message").stop().css('display','block').css('color','red').html(error.name + ' ' + error.message).fadeOut(1500);
				jQuery("#captchelfie_do").prop( "disabled", false );
			}
			
		});	//Webcam.snap
		
		return false;
	}//captchelfie_detect_face()	
	
	function drawFacesAddPoint(control, imgWidth, imgHeight, point, title) {
		var x = Math.round(point.x * imgWidth / 100);
		var y = Math.round(point.y * imgHeight / 100);
		var pointClass = title == null ? "captchelfie_api_face_all_point" : "captchelfie_api_face_point";	
		var pointStyle = 'top:' + y + 'px;left:' + x + 'px;';	
		var pointTitle = (title == null ? '' : title + ': ') + 'X=' + x + ', Y=' + y + ', Confidence=' + point.confidence + '%' + (title == null ? ', Id=' + point.id.toString(16) : '');
		var html_in = '<span class="' + pointClass + '" style="' + pointStyle + '" title="' + pointTitle + '"></span>'	
		control.append(jQuery(html_in));
	}

	function captchelfie_disable_submit(do_disable)
	{
		if( (jQuery('#captchelfie-preview').length > 0) )//in captchelfie-config.php
		{
			return; //standard not found, or it's demo mode - do nothing
		}
		if(do_disable)
		{
			jQuery('#captchelfie_block_wrap').closest("form").find('input[type=submit]').prop( "disabled",  true).css('cursor','not-allowed').css({ opacity: 0.2 });//we disable submit	
		}
		else
		{
			jQuery('#captchelfie_block_wrap').closest("form").find('input[type=submit]').prop( "disabled",  false).css('cursor','auto').css({ opacity: 1.0 });//we disable submit	
		}
	}
	
	function processFaces(div, photo, drawPoints) {
		if (!photo) {
			alert("No image found");
			return;
		}
		if (photo.error_message) {
			alert(photo.error_message);
			return;
		}
        div.html('<div class="captchelfie_image_wrapper"></div>');
		var imageWrapper = jQuery('.captchelfie_image_wrapper');
		
		var frame_shrink = 20; //TROF 
		var size_shift = 2.0; //TROF 1.5
		var pos_shift = 0.5; //TROF 0.5		
		
		var maxImgWidth = div.width();
		var imgWidth = photo.width;
		var imgHeight = photo.height;
        var scaleFactor = maxImgWidth / imgWidth; 
		if (scaleFactor < 1) {
			imgWidth = Math.round(imgWidth * scaleFactor);
			imgHeight = Math.round(imgHeight * scaleFactor);
		}
		imageWrapper.append(jQuery('<img style="width:'+main_width+'px;height:'+main_height+'px;" src="' + photo.url + '"></img>'));
		if ( photo.tags && photo.tags.length > 0 ) {
			for (var i = 0; i < photo.tags.length; ++i) {
				var tag = photo.tags[i];
				var tagWidth = tag.width * size_shift; //1.5
				var tagHeight = tag.height * size_shift;//1.5
				var width = Math.round(tagWidth * imgWidth / 100) - (frame_shrink * 2);
				var height = Math.round(tagHeight * imgHeight / 100)  - (frame_shrink * 2);
				var left = Math.round((tag.center.x - pos_shift * tagWidth) * imgWidth / 100) + frame_shrink; //0.5
				var top = Math.round((tag.center.y - pos_shift * tagHeight) * imgHeight / 100) + frame_shrink; //0.5
				if (drawPoints && tag.points) {
					for (var p = 0; p < tag.points.length; p++) {
						drawFacesAddPoint(imageWrapper, imgWidth, imgHeight, tag.points[p], null);
//console.log(tag.points[p]);						
					}
				}
				var tagStyle = 'top: ' + top + 'px; left: ' + left + 'px; width: ' + width + 'px; height: ' + height + 'px; transform: rotate(' +
					tag.roll + 'deg); -ms-transform: rotate(' + tag.roll + 'deg); -moz-transform: rotate(' + tag.roll + 'deg); -webkit-transform: rotate(' +
					tag.roll + 'deg); -o-transform: rotate(' + tag.roll + 'deg)';
				var apiFaceTag = jQuery('<div class="captchelfie_api_face_frame" style="' + tagStyle + '"><div class="api_face_inner_tid" name="' + tag.tid + '"></div></div>').appendTo(imageWrapper);
				if (drawPoints) {
					if (tag.eye_left) drawFacesAddPoint(imageWrapper, imgWidth, imgHeight, tag.eye_left, "Left eye");
					if (tag.eye_right) drawFacesAddPoint(imageWrapper, imgWidth, imgHeight, tag.eye_right, "Right eye");
					if (tag.mouth_center) drawFacesAddPoint(imageWrapper, imgWidth, imgHeight, tag.mouth_center, "Mouth center");
					if (tag.nose) drawFacesAddPoint(imageWrapper, imgWidth, imgHeight, tag.nose, "Nose tip");
				}
				
				var title = 'face';
				var attributes = '';
				var tbody = '<table class="captchelfie_api_face_facts">';
				
				if (tag.attributes) {
					if (tag.attributes.face && (tag.attributes.face.confidence > 0) ) {
                        title += ': (' + tag.attributes.face.confidence + '%)';
                        tbody += '<tr><td>Face</td><td>' + tag.attributes.face.confidence + '%</td>';
                    }
					if (tag.attributes.gender) {
                        attributes += 'gender: ' + tag.attributes.gender.value + ( tag.attributes.gender.confidence ? ' (' + tag.attributes.gender.confidence + '%)' : '' ) + '<br/>';
                        tbody += '<tr><td>Gender</td><td>' + tag.attributes.gender.value + '</td></tr>'; 
                    }
					if (tag.attributes.smiling) {
                        attributes += 'smiling: ' + tag.attributes.smiling.value + ' (' + tag.attributes.smiling.confidence + '%)<br/>';
                        tbody += '<tr><td>Smiling</td><td>' + tag.attributes.smiling.value + '</td></tr>'; 
                    }
					if (tag.attributes.glasses) {
                        attributes += 'glasses: ' + tag.attributes.glasses.value + ( tag.attributes.glasses.confidence ? ' (' + tag.attributes.glasses.confidence + '%)' : '' ) + '<br/>';
                        tbody += '<tr><td>Glasses</td><td>' + tag.attributes.glasses.value + '</td></tr>'; 
                    }
					if (tag.attributes.dark_glasses) {
                        attributes += 'dark_glasses: ' + tag.attributes.dark_glasses.value + ( tag.attributes.dark_glasses.confidence ? ' (' + tag.attributes.dark_glasses.confidence + '%)' : '' ) + '<br/>';
                        tbody += '<tr><td>Dark glasses</td><td>' + tag.attributes.dark_glasses.value + '</td></tr>'; 
                    }
					if (tag.attributes.lips) {
                        attributes += 'lips: ' + tag.attributes.lips.value + ( tag.attributes.lips.confidence ? ' (' + tag.attributes.lips.confidence + '%)' : '' ) + '<br/>';
                        tbody += '<tr><td>Lips</td><td>' + tag.attributes.lips.value + '</td></tr>'; 
                    }
					if (tag.attributes.eyes) {
                        attributes += 'eyes: ' + tag.attributes.eyes.value + ( tag.attributes.eyes.confidence ? ' (' + tag.attributes.eyes.confidence + '%)' : '' ) + '<br/>';
                        tbody += '<tr><td>Eyes</td><td>' + tag.attributes.eyes.value + '</td></tr>'; 
                    }
					if (tag.attributes.age_est) attributes += 'age: ' + tag.attributes.age_est.value + '<br/>';
					if (tag.attributes.mood) {
						attributes += 'mood: ' + tag.attributes.mood.value;
                        if(tag.attributes.mood.confidence) {
                            attributes += ' (' + tag.attributes.mood.confidence + '%)<br/>';
                            attributes += '&nbsp;&nbsp;N: ' + tag.attributes.neutral_mood.confidence + '%, A: ' + tag.attributes.anger.confidence + '%, D: ' + tag.attributes.disgust.confidence + '%, F: ' + tag.attributes.fear.confidence + '%<br/>';
                            attributes += '&nbsp;&nbsp;H: ' + tag.attributes.happiness.confidence + '%, S: ' + tag.attributes.sadness.confidence + '%, SP: ' + tag.attributes.surprise.confidence + '%'; 
                        }
                        attributes += '<br/>';
                        tbody += '<tr><td>Mood</td><td>' + tag.attributes.mood.value + '</td></tr>'; 
                    }
                    tbody += '<tr><td>Roll</td><td>' + tag.roll + '&deg;</td></tr>';
                    tbody += '<tr><td>Yaw</td><td>' + tag.yaw + '&deg;</td></tr>';
                    tbody += '</table>';
					return tbody;
				}
				attributes += 'roll: ' + tag.roll + '&deg;, yaw: ' + tag.yaw + '&deg;';
			}
		}else{
			//not detected
        }
		return '';
	}


//service functions - gonna save some stuff in cookies,
function set_cookie(c_name,value,exdays)
{
	var exdate=new Date();
	exdate.setDate(exdate.getDate() + exdays);
	var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
	document.cookie=c_name + "=" + c_value + ";path=/;";
}
function get_cookie(c_name)
{
	var c_value = document.cookie;
	var c_start = c_value.indexOf(" " + c_name + "=");
	if (c_start == -1)
	{
		c_start = c_value.indexOf(c_name + "="); 
	}
	if (c_start == -1)
	{
		c_value = null;
	}
	else
	{
		c_start = c_value.indexOf("=", c_start) + 1;
		var c_end = c_value.indexOf(";", c_start);
		if (c_end == -1)
		{
			c_end = c_value.length;
		}
		c_value = unescape(c_value.substring(c_start,c_end));
	}
	return c_value;
}
}( window.captchelfie_code_block = window.captchelfie_code_block || {}, jQuery ));	//namespace - end

//called from outside
function captchelfie_aqh(jq_selector, qtip_help_text) //Attach Qtip elp - just for readability
{
	jQuery(jq_selector).qtip({content: {text:qtip_help_text,button: true,},position: { at: 'center center', },hide: {delay: 5000, },	});  
}