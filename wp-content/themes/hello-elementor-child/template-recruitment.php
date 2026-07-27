<?php
/**
 * Template Name: Recruitment
 *
 * Recruitment index — lists open job postings (the `recruitment` CPT) using the
 * verbatim ARKRAY markup. Individual jobs render via single-recruitment.php.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$arkray_page_title    = arkray_t( 'Recruitment' );
$arkray_current_job_id = 0;
include locate_template( 'parts/recruitment-header.php' );
?>

	<div id="content_area">
		<h1 class="h1_index"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></h1>

		<div id="editor_area">
			<?php
			$rec_query = new WP_Query( array(
				'post_type'      => 'recruitment',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
				'lang'           => '',
			) );

			if ( $rec_query->have_posts() ) :
				while ( $rec_query->have_posts() ) :
					$rec_query->the_post();
					$job_intro = arkray_get_recruitment_intro( get_the_ID() );
					$job_url   = get_permalink();
					?>
					<h3 class="h3_content"><a href="<?php echo esc_url( $job_url ); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
					<p class="bold">Job Description:</p>
					<?php if ( '' !== $job_intro ) : ?>
						<?php echo esc_html( $job_intro ); ?>

					<?php endif; ?>
					<p class="right_more"><a href="<?php echo esc_url( $job_url ); ?>"><?php echo esc_html( arkray_t( 'more' ) ); ?></a></p>
					<?php
				endwhile;
				wp_reset_postdata();
			else :
				?>
				<p>Currently there are no open positions.</p>
				<?php
			endif;
			?>
		</div>

		<div class="contact_area">
			<p><a href="mailto:job@arkray.ph"><?php echo esc_html( arkray_t( 'Contact Us' ) ); ?></a></p>
		</div>
	</div>

<?php
include locate_template( 'parts/recruitment-footer.php' );
