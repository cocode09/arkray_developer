<?php
/**
 * One-off migration: create the Recruitment job postings (recruitment CPT)
 * with content scraped from arkray.ph, bound to ACF fields
 * (job_intro + job_sections repeater).
 *
 * Run: php wp-content/themes/hello-elementor-child/tools/migrate-recruitment-content.php
 */

$root = dirname( __FILE__, 5 );
require_once $root . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WP not loaded\n" );
}

$office = 'Lot 22 Phase 1A, First Philippine Industrial Park, Sta. Anastacia, Sto. Tomas, Batangas 4234';

$ul = static function ( array $items ) {
	$lis = array_map( static function ( $i ) {
		return '<li>' . $i . '</li>';
	}, $items );
	return "<ul>\n" . implode( "\n", $lis ) . "\n</ul>";
};

$jobs = array(
	array(
		'slug'  => 'finance-and-admin-head-1',
		'title' => 'FINANCE AND ADMIN HEAD (1)',
		'order' => 0,
		'intro' => '* Graduate of Bachelor of Science in Accountancy. Licensed CPA (Certified Public Accountant); With at least 3-4 years experience in managerial level gained in a manufacturing set-up.',
		'sections' => array(
			array( 'Requirements', $ul( array(
				'Graduate of Bachelor of Science in Accountancy',
				'Licensed CPA (Certified Public Accountant)',
				"With at least 3-4years' experience at the managerial level",
			) ) ),
			array( 'Skills Required', $ul( array(
				'Knowledgeable in GAAP, PAS, PFRS, Philippine Taxation Laws, and Labor Laws',
				'Knowledgeable in ERP system; SAP Systems',
				'With excellent decision-making skills, strong leadership and communication proficiency',
			) ) ),
			array( 'Duties and Responsibilities', $ul( array(
				'Responsible in all activities to Finance &amp; Accounting dealing with Financial Reporting, Budget Management, Manufacturing Costing, Tax Compliance Management. Manage various administrative activities of Administration Division covering human resource, general affairs, and health and safety.',
				'Review, analyzes, and reconciles company budgets and ensures appropriate management and allocation of budget.',
				'Liaises with and manages engagement with external financial auditors and other regulatory bodies.',
				'Collaborates with legal to ensure compliance to statutory and other requirements.',
				'Reviews and approves all vouchers and ensures the accuracy of supporting documents.',
				'Reviews and provides management reports such as profit and losses.',
				'Maintain all records of banks and legal structures and ensures that company financial records are updated.',
				'Reviews and approves disbursement and ensures validity and appropriately documented.',
				'Manages end-to-end closing calendar and reviews all transactions and prepares necessary reports.',
				'Prepares monthly, quarterly, and annual Financial Statements in accordance with Accounting Standards.',
				'Liaises with headquarters matters relating to budget, account settlement, audits, and other necessary communications required.',
				'Manages bookkeeping of the general ledger, accounts payable, account receivables, fixed assets, and payrolls.',
				'Oversees payroll function to ensure that employees are paid in a timely and accurate manner.',
				'Ensures that end-to-end service of the Admin Division Team is delivered promptly and accurately.',
				'Provides direction in compliance with legal, contractual, or any statutory procedures relating to personnel management.',
				'Develop corporate strategies to address current challenges and future issues concerning manpower retention and turnovers.',
			) ) ),
			array( 'Office Location', '<p>' . $office . '</p>' ),
		),
	),
	array(
		'slug'  => 'general-accounting-supervisor-1',
		'title' => 'GENERAL ACCOUNTING SUPERVISOR (1)',
		'order' => 1,
		'intro' => '* Graduate of Bachelor of Science in Accountancy; With at least 3 years experience in supervisory level gained in a manufacturing set-up.',
		'sections' => array(
			array( 'Requirements', $ul( array(
				'Graduate of Bachelor of Science in Accountancy',
				'With at least 3 years experience in supervisory level',
			) ) ),
			array( 'Skills Required', $ul( array(
				'Knowledgeable in PEZA and BIR reports',
				'Knowledgeable in SAP or ERP and proficient in MS Excel',
				'With working knowledge in financial reporting standards.',
			) ) ),
			array( 'General Description', '<p>Assist the Accounting Manager in all finance activities relating to financial statements, budget, and expense analyses.</p>' ),
			array( 'Duties and Responsibilities', $ul( array(
				'Prepares the monthly Financial Statements (Balance Sheet / P&amp;L) and related reports on time and with accuracy',
				'Ensures the timely and accurate recording of assets, liabilities, revenues, and expenses by the general accounting team in accordance with accounting standards in effect',
				'Prepares monthly schedules of expenses, accrued expenses, and other payables.',
				'Perform account reconciliation for balance sheets and expense accounts.',
				'Ensures timely and accurate filing of tax returns and required attachments to BIR',
				'Supervises the work of the general accounting team, ensures that accounting policies and work instructions are adhered to, and resolves discrepancies and errors with the team and other concerned parties',
				'Assists in process improvements and establishing a sound internal control system.',
				'Assists in monthly analysis of PL results and audits.',
				'Coordinates with other teams and external parties for required data and documents.',
			) ) ),
			array( 'Office Location', '<p>' . $office . '</p>' ),
		),
	),
	array(
		'slug'  => 'warehouse-team-leader-1',
		'title' => 'WAREHOUSE TEAM LEADER (1)',
		'order' => 2,
		'intro' => '* Graduate of Bachelor’s Degree of any Four-year Course; With at least 2 years experience in gained in a manufacturing set-up.',
		'sections' => array(
			array( 'Requirements', $ul( array(
				'Graduate of Bachelor',
				'With at least 3 years experience in supervisory level',
			) ) ),
			array( 'Skills Required', $ul( array(
				'Knowledgeable in SAP or ERP and proficient in MS Excel',
				'With good leadership skills',
				'Proficient in oral and written communication',
			) ) ),
			array( 'General Description', '<p>Responsible for warehouse activities from receiving, inventory, and issuances of materials.</p>' ),
			array( 'Duties and Responsibilities', $ul( array(
				'Maintains 100% material availability at all times to secure the manufacturing operation by providing an effective and efficient production plan and schedule.',
				'Responsible for Daily Operation and Management of warehouse and inventory.',
				'Monitors the timely delivery of product and material needed by production.',
				'Performs efficient and accurate overseeing of stock or inventory.',
				'Weekly raw material inventory reporting to Division Manager.',
				'Responsible for training subordinates on work-related concerns.',
			) ) ),
			array( 'Office Location', '<p>' . $office . '</p>' ),
		),
	),
);

// Fix the unicode apostrophe placeholder.
foreach ( $jobs as &$j ) {
	$j['intro'] = str_replace( '’', "\xe2\x80\x99", $j['intro'] );
}
unset( $j );

foreach ( $jobs as $job ) {
	$existing = get_page_by_path( $job['slug'], OBJECT, 'recruitment' );

	$postarr = array(
		'post_type'    => 'recruitment',
		'post_status'  => 'publish',
		'post_title'   => $job['title'],
		'post_name'    => $job['slug'],
		'menu_order'   => $job['order'],
		'post_content' => '',
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
		$post_id       = wp_update_post( $postarr, true );
		echo "Updated: {$job['slug']} (ID {$existing->ID})\n";
	} else {
		$post_id = wp_insert_post( $postarr, true );
		echo "Created: {$job['slug']} (ID " . ( is_wp_error( $post_id ) ? 'ERR' : $post_id ) . ")\n";
	}

	if ( is_wp_error( $post_id ) ) {
		echo '  ! ' . $post_id->get_error_message() . "\n";
		continue;
	}

	update_field( 'job_intro', $job['intro'], $post_id );

	$rows = array();
	foreach ( $job['sections'] as $sec ) {
		$rows[] = array(
			'section_title'   => $sec[0],
			'section_content' => $sec[1],
		);
	}
	update_field( 'job_sections', $rows, $post_id );
	echo "  > intro + " . count( $rows ) . " sections bound\n";
}

echo "Done.\n";
