<?php
/*
  Plugin Name: Comment Moderation Highlights
  Plugin URI: https://github.com/forlogos/WP-comment_moderation_highlights
  Description: Highlight comments in the comments admin of WordPress
  Version: 1.0
  Author: forlogos
  Author URI: http://jasonjalbuena.com
  License: GPL V3
 */

class flgs_comment_moderation_highlight {
	private static $instance = null;
	private $plugin_path;
	private $plugin_url;

	//Creates or returns an instance of this class.
	public static function get_instance() {
		// If an instance hasn't been created and set to $instance create an instance and set it to $instance.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	//Initializes the plugin by setting hooks, filters, and functions.
	private function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );		
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_styles' ) );
		add_action( 'admin_footer', array( $this, 'print_scripts_styles'));

		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

		$this->run_plugin();
	}

    //runs at plugin activation
    public function activation() {
		add_option('flgs_cmh');
	}

    //run at plugin deactivation
    public function deactivation() {
		delete_option('flgs_cmh');
	}

    //Enqueue and register css & js in the admin
    public function register_scripts_styles($hook) {
		//let's enqueue jquery
		wp_enqueue_script('jquery');

		//register spectrum's js and css
		wp_register_style('spectrum_st', plugins_url('/scripts/spectrum.css', __FILE__));		
		wp_register_script('spectrum_sc', plugins_url('/scripts/spectrum.js', __FILE__),  array( 'jquery') );

		//enqueue spectrum's style's only on the settings=>Discussion page
		if($hook=='options-discussion.php') {
			wp_enqueue_style( 'spectrum_st' );
			wp_enqueue_script( 'spectrum_sc');
		}
	}

	//enqueue styles and scripts to print
	public function print_scripts_styles($hook) {
		//let's get the screen, so we know what page we're looking at
		$screen = get_current_screen();

		//start js section
		echo '<script type="text/javascript">jQuery(document).ready(function($) {';

			//if the page we're on is settings=>Discussion
			if($screen->base=='options-discussion') {
				//load spectrum
				echo '//count how many highlights/table rows we have
				var cmhi=$("#cmh_table tr").length;

				//function to reload spectrum for dynamically added elements
				function loadSpectrum() {
					$(".colorpick:empty").spectrum({
						showInput: true,
						preferredFormat: "hex",
						showInitial: true,
						clickoutFiresChange: true
					});
				};

				//init spectrum on non dynamic box
				loadSpectrum();

				//add a new table row/highlight
				$("#add_cmh").click(function() {

					cmhi = cmhi + "1";

					$("#cmh_table tbody").append(\'<tr><td><input type="text" name="flgs_cmh[\' + cmhi + \'][what]" value=""/></td><td><select name="flgs_cmh[\' + cmhi + \'][type]"><option value="name" >name</option><option value="email">email</option><option value="ip">IP address</option></select></td><td><input type="text" name="flgs_cmh[\' + cmhi + \'][note]" value=""/></td><td><input type="text" name="flgs_cmh[\' + cmhi + \'][color]" value="" class="colorpick"/></td><td><span class="rem_cmh">x remove</span></td></tr>\');

					loadSpectrum(); // add spectrum on the new box
				});

				//remove a table row/highlight
				$( "body" ).on("click", ".rem_cmh", function() {
					$( this ).parent().parent().fadeOut( "slow", function() {
						$(this).slideUp("2000").remove();
					});
				});';
			//else if the page we're on is the Comments page
			}elseif($screen->base=='edit-comments') {
				//copy the cmh classes to the table row and remove the original classes
				echo '$(".cmh_hilights").each(function() {
					$(this).parent().parent().addClass( $(this).attr("class")  );
					$(this).removeClass();
				});';
			}
		echo '});</script>';

		//if the page we're on is the Comments page, let's add this css
		if($screen->base=='edit-comments') {
			//start css section
			echo '<style type="text/css">';

				//get saved options
				$c=get_option('flgs_cmh');

				//if there are no options saved make $c be an array with a blank element
				$c=(!empty($c)&&$c!=''?$c:array(''));

				//set vars for looping thru saved options
				$i=1;

				//let's loop thru each one
				foreach($c as $o) {
					//set vars
					$what=(!empty($o['what'])?$o['what']:'');
					$type=(!empty($o['type'])?$o['type']:'');
					$color=(!empty($o['color'])?$o['color']:'');

					//let's only do this if $what isn't empty
					if($what!='' && $what!=' ') {
						//make a css rule for each type of highlight
						echo '.cmh_'.$type.'_'.$i.' {background:'.$color.';}';
						$i++;
					}
				}
			//end the css section
			echo '</style>';
		}
	}

	//let's show the options panel
	function register_settings() {
		add_settings_section(
			'general_settings_section',//ID
			'Comment Moderation Highlights',//title
			'section_header', //h3 title for section
			'discussion'//add to this page
		);
		add_settings_field(
			'flgs_cmh',//ID
			'',     //label
			'cmh_showadd_highlights_section',//function to show option section
			'discussion',//add this to this page
			'general_settings_section'// The name of the section to which this field belongs

		);
		register_setting( 'discussion', 'flgs_cmh' ); 
	}

    //plugin's functionality functions
    private function run_plugin() {

		//callback function for note
		function section_header() {
			echo '<p>Choose what to search for in which comment field, add a note to display, and select a color to highlight it with.</p>';
		}

		function cmh_showadd_highlights_section($args) {
			//get saved options
			$c=get_option('flgs_cmh');

			//if there are no options saved make $c be an array where the first array has what='', so that we show a blank
			$c=(!empty($c)&&$c!=''?$c:array(array('what'=>' ')));

			//set vars for looping thru saved options
			$i=1;
			$rows='';
			//let's loop thru each one
			foreach($c as $o) {

				//set vars
				$what=(!empty($o['what'])?$o['what']:'');
				$type=(!empty($o['type'])?$o['type']:'');
				$note=(!empty($o['note'])?$o['note']:'');
				$color=(!empty($o['color'])?$o['color']:'');

				//let's only do this if $what isn't empty
				if($what!='') {
					$rows .= '<tr><td>
						<input type="text" name="flgs_cmh['.$i.'][what]" value="'.trim($what).'"/>
					</td><td>
						<select name="flgs_cmh['.$i.'][type]">
							<option value="name" >name</option>
							<option value="email"'.($type=='email'?' selected':'').'>email</option>
							<option value="ip" '.($type=='ip'?' selected':'').'>IP address</option>
						</select>
					</td><td>
						<input type="text" name="flgs_cmh['.$i.'][note]" value="'.$note.'"/>
					</td><td>
						<input type="text" name="flgs_cmh['.$i.'][color]" value="'.$color.'" class="colorpick"/>
					</td><td>
						<span class="rem_cmh">x remove</span>
					</td></tr>';
					$i++;
				}
			}

			//start HTML table to output
			$html='<table id="cmh_table"><thead><tr><td>Search For</td><td>In This Field</td><td>Show This Note</td><td>Highlight In This Color</td><td></td></tr></thead>
				<tbody>';

			//add code that was looped just above
			$html .=$rows;

			//end the HTML table and add the 'add' button
			$html.='</tbody></table>			
			<span id="add_cmh">+ add a highlight</span>';

			//let's show what we got
			echo $html;
		}

		//modify the comment text column
		function flgs_show_comm_hls($text, $comment_obj) {

			//let's only do this in the admin
			if(is_admin()) {

				//$text == the comment text. show it
				echo $text;

				//set vars from the comment object (the ${$type} in the foreach below)
				$name=$comment_obj->comment_author;
				$ip=$comment_obj->comment_author_IP;
				$email=$comment_obj->comment_author_email;

				//get saved options
				$c=get_option('flgs_cmh');

				//if there are no options saved make $c be an array with a blank element
				$c=(!empty($c)&&$c!=''?$c:array(''));

				//set vars for looping thru saved options
				$i=1;//counter for each option
				$ic=0;//counter for each highlight found for each comment

				//let's loop thru each highlight that was set
				foreach($c as $o) {
					//set vars for each highlight that was set
					$what=(!empty($o['what'])?trim($o['what']):'');
					$type=(!empty($o['type'])?$o['type']:'');
					$note=(!empty($o['note'])?$o['note']:'');
					$color=(!empty($o['color'])?$o['color']:'');
					
					//let's only do this if $what isn't empty
					if($what!='' && $what!=' ') {
						//is there are match with what we're looking for?
						if(stripos(${$type}, $what) !== false) {//if there is a match...

							//add to the highlight for each comment counter
							$ic++;

							//if this is the first highlight matched for this comment...
							if($ic==1) {
								//add a space after the comment text
								echo '<br><br>';
							}else{
								echo ', ';//if not, add a comma before the note
							}
							//show the $note and add a class for css/jquery use
							echo '<strong class="cmh_'.$type.'_'.$i.' cmh_hilights">'.$note.'</strong>';
						}
						//add to the each option counter
						$i++;
					}
				}
			}
		}
		add_action('comment_text', 'flgs_show_comm_hls', 1, 2 );
	}
}

flgs_comment_moderation_highlight::get_instance();