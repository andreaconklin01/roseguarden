<?php
/**
 * Data is intentionally retained on uninstall because this plugin is an HR ledger.
 * A future, separately authenticated retention tool should perform any legally
 * approved anonymisation or destruction workflow.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
