<?php
//configuration values, so we don't have to edit main php file.

//configuration for supported captcha plugins
//block - classifier of CSS block with captcha. We need it to show/hide
// 'check_filters' from add_action()/add_filter(). 
//  we need this information to remove_filter(tag,hook,priority) if LiveCaptcha succeeded
// Priority is important! in remove_filter must be the same as in add_filter (add_action)
global $apibio_captchelfie_supported;
//Preview for admin page
	$apibio_captchelfie_supported = array( 
		"Live Captcha Preview" =>
			array(
				'block' => '#captchelfie-preview', //we also relay on this one to detect demo mode
				'check_filters' => 
					array(
						'priority' => 0,
						'tag' => 'dummy',
						'hook' => 'dummy',					
					),
			),
//Google Captcha (reCAPTCHA) by BestWebSoft
		'Google Captcha (reCAPTCHA) by BestWebSoft'	=> 
			array ( 
				'block' => ".gglcptch_recaptcha:first", 
				'check_filters' => 
				array(	
					array( 
						'priority' => 21,
						'tag' => 'authenticate',
						'hook' => 'gglcptch_login_check',
					),
					array( 
						'priority' => 10,
						'tag' => 'allow_password_reset',
						'hook' => 'gglcptch_lostpassword_check',
					),
					array( 
						'priority' => 10,
						'tag' => 'registration_errors',
						'hook' => 'gglcptch_lostpassword_check',
					),		
					array( 
						'priority' => 10,
						'tag' => 'pre_comment_on_post',
						'hook' => 'gglcptch_commentform_check',
					),	
					array( 
						'priority' => 10,
						'tag' => 'cntctfrm_check_form',
						'hook' => 'gglcptch_recaptcha_check',
					),		
					array( 
						'priority' => 10,
						'tag' => 'cntctfrmpr_check_form',
						'hook' => 'gglcptch_recaptcha_check',
					),					
				),//Google Captcha (reCAPTCHA) by BestWebSoft
			),

//Captcha by BestWebSoft
		'Captcha by BestWebSoft'	=> 
			array ( 
				'block' => ".cptch_block:first", 
				'check_filters' => 
				array(	
					array( 
						'priority' => 21,
						'tag' => 'authenticate',
						'hook' => 'cptch_login_check',
					),
					array( 
						'priority' => 10,
						'tag' => 'allow_password_reset',
						'hook' => 'cptch_lostpassword_check',
					),
					array( 
						'priority' => 10,
						'tag' => 'registration_errors',
						'hook' => 'cptch_register_check',
					),		
					array( 
						'priority' => 10,
						'tag' => 'comment_form_after_fields',
						'hook' => 'cptch_comment_form_wp3',
					),	
					array( 
						'priority' => 10,
						'tag' => 'comment_form_logged_in_after',
						'hook' => 'cptch_comment_form_wp3',
					),	
					array( 
						'priority' => 10,
						'tag' => 'comment_form',
						'hook' => 'cptch_comment_form',
					),						
					array( 
						'priority' => 10,
						'tag' => 'cntctfrm_check_form',
						'hook' => 'cptch_check_custom_form',
					),		
					array( 
						'priority' => 10,
						'tag' => 'cntctfrmpr_check_form',
						'hook' => 'cptch_check_custom_form',
					),					
				),//Google Captcha (reCAPTCHA) by BestWebSoft
			),			

		"Contact Form 7 recaptcha" =>
			array(
				'block' => '.wpcf7-recaptcha', //we also relay on this one to detect demo mode
				'check_filters' => 
					array(
						'priority' => 9,
						'tag' => 'wpcf7_spam',
						'hook' => 'wpcf7_recaptcha_check_with_google',					
					),
			),	//Contact Form 7 recaptcha		
			
	); //$apibio_captchelfie_supported
