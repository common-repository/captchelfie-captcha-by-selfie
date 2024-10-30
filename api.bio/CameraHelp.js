//Copyright (c) API.BIO, 2016
//version 0.0.1
//this very simple script opens camera help accordingly to the current browser
//relies on the presents of jQuery
//usage 
// include this js
// call CameraHelp(help_link_selector); , where 'help_link_selector' is jquery selector for the elemnt the help link suppose to be attached to 
function CameraHelp(help_link_selector)
{
	var div_link_selector = "#apibio_camera_help_link"; //default
	if (typeof help_link_selector != "undefined" && help_link_selector != undefined)
	{
		div_link_selector = help_link_selector;
	}
	var user_agent = navigator.userAgent;
	var help_url = '';
	if(user_agent.indexOf('Edge') != -1 ) //Includes Chrome!
	{
		help_url = ''; //can not fing dicent help for the Edge Camera so far =(
	}	
	else if(user_agent.indexOf('Chrome') != -1 ) //includes Safari!
	{
		help_url = 'https://support.google.com/chrome/answer/2693767';   
	}	
	else if(user_agent.indexOf('Trident') != -1 ) //ie 11. Clear
	{
		help_url = 'https://helpx.adobe.com/flash-player/kb/install-flash-player-windows.html';
	}	
	else if(user_agent.indexOf('Opera') != -1 )//Clear 
	{
		help_url = 'http://help.opera.com/Windows/12.10/en/camera.html';
	}
	else if(user_agent.indexOf('Safari') != -1 ) //Clear
	{
		help_url = 'https://helpx.adobe.com/flash-player/kb/enabling-flash-player-safari.html';
	}
	else if(user_agent.indexOf('Firefox') != -1 )//Clear
	{
		help_url = 'https://support.mozilla.org/en-US/kb/page-info-window-view-technical-details-about-page#w_permissions';
	}
//here we have help URL for the current browser
	if(jQuery(div_link_selector).length > 0 ) //exists
	{
		if(help_url != '') //we do have help for this browser
		{
			jQuery(div_link_selector).on('click',function(event){
				event.preventDefault();	
				window.open(help_url,'CameraHelp');
			});
		}
		else
		{
			jQuery(div_link_selector).css('display','none'); //hide it
		}	
	}//we have help url
	
	
}//CameraHelp