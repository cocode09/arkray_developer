<?php
/**
 * Template Name: Media Gallery
 *
 * Media Gallery is the second tab of Events & Gallery. This template pins the
 * shared Events & Gallery shell to that tab so the section can be managed as a
 * regular page under Pages.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$arkray_force_events_gallery_tab = 'gallery';

require get_stylesheet_directory() . '/template-events-gallery.php';
