<?php
/**
 * ARKRAY Group interactive Google Map (gmap.js).
 *
 * Expects in scope (set by template-about-us.php):
 *   $map_area      string  area value for gmap.js ("World"|"Japan"|"Asia"|"Europe"|"US")
 *   $active_region string  region label to mark active in the tabs
 *   $group_url, $group2_url, $group3_url, $group4_url, $group5_url  region page URLs
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$theme_uri    = get_stylesheet_directory_uri();
$maps_api_url = 'https://maps.google.com/maps/api/js?sensor=false&language=en&key=AIzaSyB_y8CFki59MD3dXOcPiqPM5RZGjtQfKaM';
?>
<h1 class="h1_index" id="h1">ARKRAY Group</h1>
<div id="gmap"></div>
<p class="gmap_lead">On this page you can view information for each facility in the ARKRAY Group.<br />
It is possible to display facilities based on region or field of operations.<br />
If you click any of the pins on the map, the facility name and a description of the field of operations will be displayed for the selected facility.</p>
<?php arkray_render_group_region_tabs( $active_region ); ?>
<ul class="gmap_tab">
	<li class="btn01 ac">ALL</li>
	<li class="btn02">HQ /<br />Administration</li>
	<li class="btn03">Research &amp;<br />Development</li>
	<li class="btn04">Production /<br />Distribution</li>
	<li class="btn05">Sales /<br />Servicing</li>
</ul>
<p>If a specific region from the list is selected, information for each facility in the selected region will be displayed.</p>

<script src="<?php echo esc_url( $theme_uri . '/js/jquery-1.11.1.min.js' ); ?>"></script>
<script src="<?php echo esc_url( $maps_api_url ); ?>"></script>
<script>
	window.ARKRAY_JQ = window.jQuery;
	window.ARKRAY_MAP_ICON_BASE = '<?php echo esc_js( $theme_uri . '/img/' ); ?>';
	var area = "<?php echo esc_js( $map_area ); ?>";
</script>
<script src="<?php echo esc_url( $theme_uri . '/js/gmap.js' ); ?>"></script>
