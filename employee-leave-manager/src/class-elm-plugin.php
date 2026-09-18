<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Plugin {
	private static ?self $instance = null;
	private bool $booted = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$rest_controller = new ELM_REST_Controller();
		add_action( 'rest_api_init', array( $rest_controller, 'register_routes' ) );
		add_action( 'wp_ajax_elm_submit_request', array( $rest_controller, 'ajax_create_request' ) );
		add_action( 'wp_ajax_elm_update_request', array( $rest_controller, 'ajax_update_request' ) );
		add_action( 'wp_ajax_elm_get_request', array( $rest_controller, 'ajax_get_request' ) );
		add_action( 'wp_ajax_elm_portal_snapshot', array( $rest_controller, 'ajax_portal_snapshot' ) );
		add_action( 'wp_ajax_elm_calendar_data', array( $rest_controller, 'ajax_calendar_data' ) );
		add_action( 'wp_ajax_elm_cancel_request', array( $rest_controller, 'ajax_cancel_request' ) );
		add_action( 'wp_ajax_elm_delete_own_request', array( $rest_controller, 'ajax_delete_own_request' ) );
		add_action( 'wp_ajax_elm_chief_portal', array( $rest_controller, 'ajax_chief_portal' ) );
		add_action( 'wp_ajax_elm_entitlement_change', array( $rest_controller, 'ajax_entitlement_change' ) );
		add_action( 'admin_post_elm_submit_request_form', array( $rest_controller, 'form_create_request' ) );
		add_action( 'admin_post_elm_update_request_form', array( $rest_controller, 'form_update_request' ) );
		add_action( 'admin_post_elm_cancel_request_form', array( $rest_controller, 'form_cancel_request' ) );

		$frontend = new ELM_Frontend();
		add_action( 'init', array( $frontend, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $frontend, 'register_assets' ) );

		if ( is_admin() ) {
			$admin = new ELM_Admin();
			add_action( 'admin_menu', array( $admin, 'register_menu' ) );
			add_action( 'admin_notices', array( $admin, 'render_schema_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
			add_action( 'admin_post_elm_export_request_pdf', array( $admin, 'export_request_pdf' ) );
			add_action( 'admin_post_elm_export_employee_pdf', array( $admin, 'export_employee_pdf' ) );
			add_action( 'admin_post_elm_download_medical', array( $admin, 'download_medical' ) );
			add_action( 'admin_post_elm_export_requests_csv', array( $admin, 'export_requests_csv' ) );
		}

		ELM_Employee_Profile::register();
		ELM_Access::register();
		ELM_Privacy::register();
		ELM_Notifications::register();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'employee-leave-manager', false, dirname( plugin_basename( ELM_FILE ) ) . '/languages' );
	}
}
