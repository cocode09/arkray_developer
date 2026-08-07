<?php
/**
 * One-off migration: create the Website Terms of Use page (slug: terms-of-use)
 * assigned to template-terms-of-use.php, seed its body with the Terms content
 * so admins can edit it, and link it to both Polylang languages so footer links
 * resolve from English and Vietnamese.
 *
 * Run once from the project root:
 *   php wp-content/themes/hello-elementor-child/tools/migrate-terms-of-use-page.php
 */

$root = dirname( __FILE__, 5 );
require_once $root . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WP not loaded\n" );
}

// ── Default page body — mirrors parts/terms-of-use-content.php ────────────
// Stored as post_content so the admin can edit it directly in WordPress.
// Body only — the H1 is rendered from the page Title field by the template.
$default_content = <<<'HTML'
<p class="tx">Please read the following Terms of Use and agree to them before using this website. By using this website, you acknowledge that you have read and agreed to this Terms of Use. If you do not agree to this Terms of Use, you may not use this website.</p>
<h2 class="h2_content">Copyrights</h2>
<p class="tx">All copyrights for the content on this website (including, but not limited to text, photographs, graphics, videos and sound ("Content")) belong to ARKRAY, our affiliates or third parties and are protected by Japanese copyright law, international conventions and other copyright laws. Except for the copy/citation for personal use permitted by those laws, this website and Content shall not be copied, reproduced, republished or distributed without authorization.</p>
<h2 class="h2_content">Trademarks</h2>
<p class="tx">Name and Logo of 'ARKRAY' and Names of products and services of ARKRAY and ARKRAY Group companies on this site are trademarks or registered trademarks of ARKRAY Inc., or ARKRAY Group companies.</p>
<p class="tx">Reproduction or appropriation of trademarks on this site without any permission in any manner is prohibited.</p>
<h2 class="h2_content">Disclaimer</h2>
<p class="tx">Content on this website is provided on an "as is" basis and ARKRAY makes no representation and warranty, either express or implied of any kind, including but not limited to accuracy, usability, credibility. In no event shall ARKRAY, our affiliates or the third parties be liable for any damages arising out of the use or inability to use this website. Please note that Content, file name, etc. on this website, as well as this Terms of Use may be changed without prior notice.</p>
<h2 class="h2_content">Non-Confidential Information</h2>
<p class="tx">All information sent to us by e-mail, etc. shall be deemed to be non-confidential. ARKRAY shall be free to use any information sent (ideas, proposals, etc.) for any purpose whatsoever, including, but not limited to, developing services and/or products, manufacturing, marketing, etc. In such cases, ARKRAY shall not be liable to pay compensation, etc. to the sender of such information.</p>
<h2 class="h2_content">Applicable Law ⁄ Court of Jurisdiction</h2>
<p class="tx">This Terms of Use shall be governed by and construed in accordance with the laws of Japan without reference to its conflicts of law rules. Any dispute arising out of or in connection with this website and/or this Terms of Use shall be subject to exclusive jurisdiction of the Kyoto district court of Japan.</p>
HTML;

// ── Guard: skip if the page already exists (published or draft) ────────────
$existing = get_page_by_path( 'terms-of-use', OBJECT, 'page' );
if ( ! ( $existing instanceof WP_Post ) ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' LIMIT 1",
		'terms-of-use'
	) );
	if ( $row ) {
		$existing = get_post( (int) $row->ID );
	}
}

if ( $existing instanceof WP_Post ) {
	echo "Website Terms of Use page already exists (ID {$existing->ID}).\n";

	// Ensure the page template is set correctly even on an existing page.
	$current_template = get_post_meta( $existing->ID, '_wp_page_template', true );
	if ( 'template-terms-of-use.php' !== $current_template ) {
		update_post_meta( $existing->ID, '_wp_page_template', 'template-terms-of-use.php' );
		echo "Updated page template to template-terms-of-use.php.\n";
	} else {
		echo "Page template already correct.\n";
	}

	// Seed body only when it is still empty (don't overwrite admin edits).
	if ( '' === trim( $existing->post_content ) ) {
		wp_update_post( array(
			'ID'           => $existing->ID,
			'post_content' => $default_content,
		) );
		echo "Seeded page body with default Terms content.\n";
	} else {
		echo "Page body already has content — skipping seed.\n";
	}
} else {
	// ── Create the page ────────────────────────────────────────────────────
	global $wpdb;
	$draft_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' LIMIT 1",
		'terms-of-use'
	) );

	if ( $draft_row ) {
		$page_id = wp_update_post( array(
			'ID'           => (int) $draft_row->ID,
			'post_status'  => 'publish',
			'post_content' => $default_content,
		), true );
	} else {
		$page_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Website Terms of Use',
			'post_name'    => 'terms-of-use',
			'post_content' => $default_content,
		), true );
	}

	if ( is_wp_error( $page_id ) ) {
		exit( 'Failed to create page: ' . $page_id->get_error_message() . "\n" );
	}

	update_post_meta( $page_id, '_wp_page_template', 'template-terms-of-use.php' );
	echo "Created Website Terms of Use page (ID {$page_id}).\n";
	$existing = get_post( $page_id );
}

$page_id = (int) $existing->ID;

// ── Polylang: set language to English and link Vietnamese to the same page ──
if ( function_exists( 'pll_set_post_language' ) && function_exists( 'pll_save_post_translations' ) ) {
	$en_slug = 'english';
	$vi_slug = 'vietnamese';

	if ( function_exists( 'pll_languages_list' ) ) {
		$langs = pll_languages_list( array( 'fields' => 'slug' ) );
		foreach ( (array) $langs as $slug ) {
			if ( false !== strpos( strtolower( $slug ), 'en' ) ) {
				$en_slug = $slug;
			}
			if ( false !== strpos( strtolower( $slug ), 'vi' ) ) {
				$vi_slug = $slug;
			}
		}
	}

	pll_set_post_language( $page_id, $en_slug );
	echo "Set page language to '{$en_slug}'.\n";

	// Link the same English page as the Vietnamese translation so
	// arkray_pll_permalink() resolves correctly from both language footers.
	pll_save_post_translations( array(
		$en_slug => $page_id,
		$vi_slug => $page_id,
	) );
	echo "Linked '{$vi_slug}' translation to the same page (ID {$page_id}).\n";
} else {
	echo "Polylang not active — skipping language assignment.\n";
}

echo "Done.\n";
