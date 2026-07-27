<?php
/**
 * Single job posting rendered from the standard WordPress editor.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( have_posts() ) :
	the_post();

	$arkray_page_title     = get_the_title();
	$arkray_current_job_id = get_the_ID();
	include locate_template( 'parts/recruitment-header.php' );
	?>

	<div id="content_area">
		<h1 class="h1_content"><?php echo esc_html( get_the_title() ); ?></h1>

		<div id="editor_area">
			<?php
			$source_matched_jobs = array(
				'finance-and-admin-head-1',
				'general-accounting-supervisor-1',
				'warehouse-team-leader-1',
			);
			if ( in_array( get_post_field( 'post_name', get_the_ID() ), $source_matched_jobs, true ) ) {
				// Keep the source site's legacy HTML intact. The normal the_content
				// filter adds paragraphs around its loose text and comments, changing
				// the spacing and browser error-recovery behavior.
				echo get_post_field( 'post_content', get_the_ID(), 'raw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				the_content();
			}
			?>

			<div class="contact_area">
				<p><a href="<?php echo esc_url( arkray_get_contact_page_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( arkray_t( 'Contact Us' ) ); ?></a></p>
			</div>
		</div>
	</div>

	<?php
	include locate_template( 'parts/recruitment-footer.php' );

endwhile;
