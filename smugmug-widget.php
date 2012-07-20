<?php

/*
 *  Plugin Name:    SmugMug Widget
 *  Plugin URI:     http://www.pmkelly.com/smugmug-widget
 *  Description:    Simple widget for displaying most recent SmugMug images.
 *  Version:        1.0
 *  Author:         Patrick M. Kelly
 *  Author URI:     http://www.pmkelly.com
 */

/*  tabstop=4  */

/* ------------------------------------------------------------------------- */
/* ------------------------------------------------------------------------- */
/* ------------------------------------------------------------------------- */

//
//  Global access to plugin database table. 
//

global $smugmug_widget_table      ;
global $smugmug_widget_db_version ;
global $wpdb                      ;

$smugmug_widget_table      = $wpdb->prefix . 'smugmug_widget' ;
$smugmug_widget_db_version = '1.0' ;

/* ------------------------------------------------------------------------- */

register_activation_hook ( __FILE__, 'smugmug_widget_install' ) ;

/* ------------------------------------------------------------------------- */

function smugmug_widget_install ( ) 
{
	//
	//	Create plugin table within database if it does not already exist.
	//	

	global $wpdb                      ;
	global $smugmug_widget_table      ;
	global $smugmug_widget_db_version ;

	if ( $wpdb->get_var( "show tables like '$smugmug_widget_table'" ) != 
											$smugmug_widget_table   ) {

		$sql = "CREATE TABLE $smugmug_widget_table (".
								"id int NOT NULL AUTO_INCREMENT, ".
								"user_text text NOT NULL, ".
								"UNIQUE KEY id (id) ".
				")";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ) ;

		dbDelta( $sql ) ;

		add_option( "smugmug_widget_db_version", $smugmug_widget_db_version ) ;

	}
}

/* ------------------------------------------------------------------------- */

//
//	Definition for the widget class.
//

class SmugMug_Widget extends WP_widget 
{
	//
	//	Constructor
	//

	function SmugMug_Widget ( )
	{
		parent::WP_widget ( 'smugmug_widget', 'SmugMug Widget' ) ;
	}

	//
	//	Function to pull instance parameters from database.  (NOTE: I am 
	//	thinking it might be more consistent to put the checks inside of 
	//	the update function, so they take place before items are stored
	//	in the database.)
	//

	function get_instance_parameters ( & $instance, & $title, & $rssfeed, 
		& $num_to_display, & $fixed_width ) 
	{
		//	Title

		if ( isset ( $instance [ 'title' ] ) ) {
			$title = $instance [ 'title' ] ;
		} else {
			$title = __( 'SmuMug Latest Additions', 'text_domain' ) ;
		}

		//	URL for RSS feed

		if ( isset ( $instance [ 'rssfeed' ] ) ) {
			$rssfeed = $instance [ 'rssfeed' ] ;
		} else {
			$rssfeed = __( 'RSS Feed URL', 'text_domain' ) ;
		}

		//	Number of images to display (minimum 1, default 8 )

		if ( isset ( $instance [ 'num_to_display' ] ) ) {

			$num_to_display = $instance [ 'num_to_display' ] ;

			if ( is_numeric ( $num_to_display ) ) {
				if ( $num_to_display <= 0 ) $num_to_display = 1 ;
			} else {
				$num_to_display = 1 ;
			}

		} else {

			$num_to_display = 8 ;

		}

		//	Display width for images (minimum 20, default 150)

		if ( isset ( $instance [ 'fixed_width' ] ) ) {

			$fixed_width = $instance [ 'fixed_width' ] ;

			if ( is_numeric ( $fixed_width ) ) {
				if ( $fixed_width <= 20 ) $fixed_width = 20 ;
			} else {
				$fixed_width = 20 ;
			}

		} else {

			$fixed_width = 150 ;

		}
	}

	//
	//	Function to update the widget (from pressing save in admin panel?)
	//

	function update ( $new_instance, $old_instance ) 
	{
		return $new_instance ;
	}

	//
	//	Display form in the admin panel for managing instance parameters.
	//

	function form ( $instance ) 
	{
		//
		//	Get parameters for current instance.
		//

		$this->get_instance_parameters ( $instance, $title, $rssfeed, 
			$num_to_display, $fixed_width ) ;

		//
		//	Title
		//

		echo '<p>' ;
		echo '<label for="' ;
		echo $this->get_field_id( 'title' ) ;
		echo '">' ;
		echo _e( 'Title:' ) ;
		echo '</label> ' ;
		echo '<input class="widefat" id="' ;
		echo $this->get_field_id( 'title' ) ;
		echo '" name="' ;
		echo $this->get_field_name( 'title' ) ;
		echo '" type="text" value="' ;
		echo esc_attr( $title ) ;
		echo '" />' ;
		echo '</p>' ;

		//
		//	RSS Feed URL
		//

		echo '<p>' ;
		echo '<label for="' ;
		echo $this->get_field_id( 'rssfeed' ) ;
		echo '">' ;
		echo _e( 'SmugMug RSS Feed URL:' ) ;
		echo '</label> ' ;
		echo '<input class="widefat" id="' ;
		echo $this->get_field_id( 'rssfeed' ) ;
		echo '" name="' ;
		echo $this->get_field_name( 'rssfeed' ) ;
		echo '" type="text" value="' ;
		echo esc_attr( $rssfeed ) ;
		echo '" />' ;
		echo '</p>' ;

		//
		//	Number of Images
		//

		echo '<p>' ;
		echo '<label for="' ;
		echo $this->get_field_id( 'num_to_display' ) ;
		echo '">' ;
		echo _e( 'Number of Images:' ) ;
		echo '</label> ' ;
		echo '<input class="widefat" id="' ;
		echo $this->get_field_id( 'num_to_display' ) ;
		echo '" name="' ;
		echo $this->get_field_name( 'num_to_display' ) ;
		echo '" type="text" value="' ;
		echo esc_attr( $num_to_display ) ;
		echo '" />' ;
		echo '</p>' ;

		//
		//	Fixed Image Width
		//

		echo '<p>' ;
		echo '<label for="' ;
		echo $this->get_field_id( 'fixed_width' ) ;
		echo '">' ;
		echo _e( 'Fixed Image Width:' ) ;
		echo '</label> ' ;
		echo '<input class="widefat" id="' ;
		echo $this->get_field_id( 'fixed_width' ) ;
		echo '" name="' ;
		echo $this->get_field_name( 'fixed_width' ) ;
		echo '" type="text" value="' ;
		echo esc_attr( $fixed_width ) ;
		echo '" />' ;
		echo '</p>' ;
	}

	//
	//	Function to display the widget
	//

	function display_images ( $rssfeed, $num_to_display, $fixed_width )
	{
		//	
		//	ABSOLUTELY WRONG PLACE FOR THIS, but it's a starting point for me
		//	to place the relevant CSS here for now.
		//

		echo '<style type="text/css">' ;
		
		echo 'div.smugmug-widget {
			padding: 0px; 0px; 0px; 0px ;
			text-align: center ;
			width: 100% ;
		}' ;
		
		echo 'img.smugmug-widget:hover {
			margin-bottom: 5% ;
			opacity: 0.7 ;
		}' ;
		
		echo 'img.smugmug-widget {
			padding: 2px; 2px; 2px; 2px ;
		
			margin-bottom: 5% ;
			opacity: 1.0 ;
			border: 1px solid rgb(200,200,200) ;
		
		}' ;
		
		echo '</style>' ;

		//
		//	Build custom size string from the specified image width.  The 
		//	size is a bounding box as far as SmugMug is concerned, so the
		//	height can be very large if needed.
		//

		$customsize = $fixed_width . "x2500" ;

		$strmod1 = '/' . $customsize . '/' ;
		$strmod2 = '-' . $customsize . '.jpg' ;

		//
		//	Get hold of the RSS feed.
		//

		$rss = fetch_feed ( $rssfeed ) ;

		if ( is_wp_error ( $rss ) ) {

			echo __( 'ERROR ACCESSING SMUGMUG RSS FEED ', 'text_domain' ) ;
			echo __( $rssfeed, 'text_domain' ) ;


		} else {

			//	Extract total number of items, and limit to "num_to_display".

			$numitems = $rss->get_item_quantity ( $num_to_display ) ;

			//	Build an array of all items, starting with element 0

			$rss_items = $rss->get_items ( 0, $numitems ) ;

			if ( $numitems == 0 ) {
				
				echo __( 'Empty Feed Found', 'text_domain' ) ;

			} else {

				foreach ( $rss_items as $item ) :

					$item_url        = $item->get_permalink  ( ) ;
					$item_title      = $item->get_title      ( ) ;
					$item_enclosure  = $item->get_enclosure  ( ) ;

					$item_thumb = $item_enclosure->get_thumbnail ( ) ;

					$item_thumb = ereg_replace ( '/Th/', $strmod1, 
									$item_thumb ) ;

					$item_thumb = ereg_replace ( '-Th.jpg', $strmod2, 
									$item_thumb ) ;

					echo '<div class="smugmug-widget">' ;

					echo '<a href="' ;
					echo $item_url ;
					echo '" target="_smugmug">' ;
					echo '<img class="smugmug-widget" src="' ;
					echo $item_thumb ;
					echo '" alt="' ;
					echo $item_title ;
					echo '">' ;
					echo '</a>' ;

					echo '</div>' ;

				endforeach ;

			}

		}
	}

	//
	//	Function to display the widget
	//

	function widget ( $args, $instance )
	{
		extract( $args ) ;

		//
		//	Get parameters for current instance.
		//

		$this->get_instance_parameters ( $instance, $title, $rssfeed, 
			$num_to_display, $fixed_width ) ;

		//
		//	Not sure how filters work, but this always seems to exist.
		//

		$title = apply_filters( 'widget_title', $title ) ;

		echo $before_widget;

		if ( ! empty ( $title ) ) echo $before_title . $title . $after_title ;

		//
		//	Call function to display the images from the feed.
		//

		$this->display_images ( $rssfeed, $num_to_display, $fixed_width ) ;

		echo $after_widget;
	}
}

/* ------------------------------------------------------------------------- */

//
//	Register widget.
//

add_action ( 'widgets_init', 'SmugMug_Widget_Init' ) ;

function SmugMug_Widget_Init ( )
{
	register_widget ( 'smugmug_widget' ) ;
}

/* ------------------------------------------------------------------------- */

?>
