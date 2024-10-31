<?php
/*
PLUGIN NAME: Prowl Me
PLUGIN URI: http://codework.dk/referencer/wp-plugins/prowl-me/
DESCRIPTION: A plugin making your visitors able to write you a direct message using <a href="http://prowl.weks.net/">Prowl</a>, a iPhone push application. Simply insert <code>[prowlme]</code> into MCE where you want it. A CodeWork plugin for WordPress.
AUTHOR: Henrik Urlund
AUTHOR URI: http://codework.dk/referencer/wp-plugins/
VERSION: 1.0.0
*/

/*
    Copyright 2007-2009 Henrik Urlund (email: henrik at codework.dk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/************************** NOW THE FUN PART **************************/

// make sure session are started
session_start();

/**
 * If possible, define constants, otherwise they need to be set!
 * (will be set on first load - they can be changed in WP admin)
 **/

define('APIKEY', get_option('prowlme_apikey'));
define('APPNAME', get_bloginfo('name'));

class prowlme
{
	/**
	 * TODO:
	 * - Backend settings
	 *   - Own msg on submit and resubmit
	 **/
	private $options;
	
        public function __construct()
        {
		// Add CSS to header
		add_action('wp_head', array(&$this, 'add_css'));
		
		// Add shortcode to plugin
		add_shortcode('prowl-me', array(&$this, 'prowlme'));
		
		// Add settings menu
		add_action('admin_menu', array($this, 'my_plugin_menu'));
		
		// add / remove settings on activation/deactivation
		register_deactivation_hook(__FILE__, array($this, 'deactivation'));
		register_activation_hook(__FILE__, array($this, 'activation'));
		
		
	}

	public function my_plugin_menu()
	{
		add_options_page('Prowl Me Options', 'Prowl Me', 8, 'prowl-me', array($this, 'options_panel'));
	}
	
	public function activation()
	{
		add_option('prowlme_apikey', '', '', 'yes');
		add_option('prowlme_nummsg', '3', '', 'yes');
	}
	
	public function deactivation()
	{
		delete_option('prowlme_apikey');
		delete_option('prowlme_nummsg');
	}
	
	/**
	 * This function adds prowl-me style sheet to the header - stylesheet can be changed in style.css
	 **/
	
	public function add_css($atts)
	{
		// Lets locate the plugin directory
		$filedir = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)); 
		echo '<link rel="stylesheet" href="'. $filedir .'style.css" type="text/css" media="screen" />';
	}
	
	/**
	 * What to do - what to do ... this will find out
	 **/
	
	public function prowlme($atts)
	{
		$txt = '
		<form method="post" action="" id="prowlme">
			<fieldset>
			<legend>Prowl Me</legend>
			';
			
			if($_POST["submitprowl"])
				$txt .= $this->prowlmsg($_POST);
			else
				$txt .= $this->buildform();
			
			$txt .= '</fieldset>
		</form>
		';
			
		return $txt;
	}
	
	/**
	 * This shows the user form in the WP frontend
	 **/
	
	private function buildform()
	{
		// make 2 random numbers to be used in spam check
		$no1 = rand(1, 9);
		$no2 = rand(1, 9);
		
		// save them - so they can be used on submit
		$_SESSION["spam_check"] = $no1 + $no2;
		
		return '
		<p>
			<label for="yourname">* Your name:</label><input type="text" name="yourname" class="prowlme_text" />
		</p>
		<p>
			<label for="yourmsg">* Message:</label><textarea name="yourmsg" class="prowlme_textarea"></textarea>
		</p>
		<p>
			<label for="spamcheck" class="label_prowlme_spamcheck">'. $no1 .'&nbsp;&nbsp;&#43;&nbsp;&nbsp;'. $no2 .'&nbsp;&nbsp;= </label><input type="text" name="spamcheck" class="prowlme_spamcheck" />
		</p>
		<p>
			<input type="submit" name="submitprowl" value="Send" class="prowlme_submit" />
			<input type="hidden" name="uniqid" value="'. md5(uniqid()) .'" />
		</p>
		';
	}
	
	/**
	 * This function will send the msg, if all conditions are meet
	 **/
	
	private function prowlmsg($post)
	{
		// check if is the first time - or the user reloaded the page
		if($_POST["uniqid"] != $_SESSION["uniqid"])
		{
			// Check if maximum prolws have been sent
			if(get_option('prowlme_nummsg') > 0)
			{
				if($_SESSION["msgsent"] >= get_option('prowlme_nummsg'))
					return '<p>Sorry, you cant send me anymore prowls right now.</p>';
			}
			
			// If you cant do the math, lets "thow" an error
			if($_POST["spamcheck"] != $_SESSION["spam_check"])
				return '<p>Sorry, take a math class and try again.</p>';
			
			// If spam check was okay lets clean up the text
			$name	= $this->cleanupmsg($_POST["yourname"]);
			$msg	= $this->cleanupmsg($_POST["yourmsg"]);
			
			// If anything is empty, lets "throw" an error
			if(strlen($name) == 0 || strlen($msg) == 0)
				return '<p>Please fill in all information.</p>';
			
			// now everything is okay - lets get the prowl
			require_once('class.prowl.php');
			$prowl = new Prowl(APIKEY, APPNAME);
			
			// now try to send the msg
			$result = $prowl->add(1, "Prowl Me", 'From: '. $name ."\nMessage: ". $msg);
			
			// now check if the msg was sent - let them know
			if(strlen($result) == 1)
			{
				// If you reach this point, the msg have been sent - lets block for refresh, by saving the uniqid
				$_SESSION["uniqid"] = $_POST["uniqid"];
				
				// Count numbers of sent msg
				$_SESSION["msgsent"]++;
				
				return '<p>Thank you for your message.</p>';
			}
			else
				return '<p>Ooops, something happend, please try again!</p><p><em>Error: "'. $result .'"</em></p>';
		}
		else
			return '<p>Please don\'t reload the page, thanks.</p>';
	}
	
	/**
	 * This function takes care of the msg format, so it will be shown the way it was intented on the iphone
	 **/
	
	private function cleanupmsg($string)
	{
		$string = str_replace("\r\n","\n", $string);
		$string = str_replace("\r","\n", $string);
		return $string;
	}
	
	public function options_panel()
	{
		echo '
		<div class="wrap">
		<h2>Prowl Me</h2>
		
		<form method="post" action="options.php">
		';
		
		wp_nonce_field('update-options');
		
		echo '
		
		<table class="form-table">
		
		<tr valign="top">
		<th scope="row">API key:</th>
		<td><input type="text" name="prowlme_apikey" value="'. get_option('prowlme_apikey') .'" /> <a href="http://prowl.weks.net/" target="_blank">Get API key</a></td>
		</tr>
		
		<tr valign="top">
		<th scope="row">Messages pr. session: </th>
		<td><input type="text" name="prowlme_nummsg" value="'. get_option('prowlme_nummsg') .'" /> 0 = unlimited</td>
		</tr>
		
		</table>
		
		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="prowlme_apikey,prowlme_nummsg" />
		
		<p class="submit">
		<input type="submit" class="button-primary" value="Save settings" />
		</p>
		
		</form>
		</div>
		';
	}
}

// make an instance of prowlme
$prowlme = new prowlme();
?>