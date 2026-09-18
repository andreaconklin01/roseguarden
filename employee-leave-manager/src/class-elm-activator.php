<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Activator {
	public static function activate(): void {
		if ( ! self::install_schema() ) {
			wp_die( esc_html__( 'Shtojca për menaxhimin e pushimeve nuk arriti t\'i krijojë tabelat e bazës së të dhënave. Kontrolloni lejet e bazës së të dhënave dhe regjistrin e gabimeve të WordPress-it, pastaj aktivizojeni përsëri shtojcën.', 'employee-leave-manager' ) );
		}
		self::install_roles();
		self::install_defaults();
		ELM_Security::ensure_keys();
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		$version_changed = ELM_DB_VERSION !== get_option( 'elm_db_version' );
		$health_check_due = false === get_transient( 'elm_schema_checked' );
		if ( ! $version_changed && ! $health_check_due ) {
			return;
		}
		if ( $version_changed || ! ELM_DB::schema_ready() ) {
			self::install_schema();
			self::install_roles();
			self::install_defaults();
			ELM_Security::ensure_keys();
			return;
		}
		set_transient( 'elm_schema_checked', 1, 12 * HOUR_IN_SECONDS );
	}

	public static function install_schema(): bool {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$requests = ELM_DB::table( 'requests' );
		$request_days = ELM_DB::table( 'request_days' );
		$balances = ELM_DB::table( 'balances' );
		$adjustments = ELM_DB::table( 'adjustments' );
		$entitlement_changes = ELM_DB::table( 'entitlement_changes' );
		$capacity = ELM_DB::table( 'capacity' );
		$audit = ELM_DB::table( 'audit' );
		$documents = ELM_DB::table( 'medical_documents' );

		$sql = array();
		$sql[] = "CREATE TABLE $requests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			employee_id bigint(20) unsigned NOT NULL,
			employee_position varchar(255) DEFAULT NULL,
			employee_sector varchar(255) DEFAULT NULL,
			leave_type varchar(20) NOT NULL,
			request_source varchar(20) NOT NULL DEFAULT 'employee',
			start_date date NOT NULL,
			end_date date NOT NULL,
			requested_units decimal(7,1) NOT NULL DEFAULT 0.0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			reason text NOT NULL,
			medical_ack tinyint(1) NOT NULL DEFAULT 0,
			medical_document_id bigint(20) unsigned DEFAULT NULL,
			submitted_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			decided_at datetime DEFAULT NULL,
			decided_by bigint(20) unsigned DEFAULT NULL,
			decision_note text NULL,
			cancelled_at datetime DEFAULT NULL,
			cancelled_by bigint(20) unsigned DEFAULT NULL,
			cancel_reason text NULL,
			version int(10) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY employee_status (employee_id,status),
			KEY status_dates (status,start_date,end_date),
			KEY submitted_at (submitted_at),
			KEY medical_document_id (medical_document_id)
		) ENGINE=InnoDB $charset;";

		$sql[] = "CREATE TABLE $request_days (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_id bigint(20) unsigned NOT NULL,
			leave_date date NOT NULL,
			units decimal(3,1) NOT NULL DEFAULT 1.0,
			period_no tinyint(1) unsigned NOT NULL,
			short_notice_warning tinyint(1) NOT NULL DEFAULT 0,
			period_one_warning tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY request_date (request_id,leave_date),
			KEY leave_date (leave_date),
			KEY request_id (request_id)
		) ENGINE=InnoDB $charset;";

		$sql[] = "CREATE TABLE $balances (
			user_id bigint(20) unsigned NOT NULL,
			leave_year smallint(5) unsigned NOT NULL,
			standard_entitlement decimal(7,1) NOT NULL DEFAULT 20.0,
			manual_added decimal(7,1) NOT NULL DEFAULT 0.0,
			approved_annual_used decimal(7,1) NOT NULL DEFAULT 0.0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (user_id,leave_year),
			KEY leave_year (leave_year)
		) ENGINE=InnoDB $charset;";

		$sql[] = "CREATE TABLE $adjustments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			leave_year smallint(5) unsigned NOT NULL,
			amount decimal(7,1) NOT NULL,
			note text NOT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_year (user_id,leave_year),
			KEY created_at (created_at)
		) ENGINE=InnoDB $charset;";

		$sql[] = "CREATE TABLE $entitlement_changes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			current_entitlement decimal(7,1) NOT NULL DEFAULT 20.0,
			requested_entitlement decimal(7,1) NOT NULL,
			effective_year smallint(5) unsigned NOT NULL,
			reason text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			requested_by bigint(20) unsigned NOT NULL,
			requested_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			decided_by bigint(20) unsigned DEFAULT NULL,
			decided_at datetime DEFAULT NULL,
			decision_note text NULL,
			version int(10) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY user_status (user_id,status),
			KEY user_year (user_id,effective_year),
			KEY effective_year (effective_year),
			KEY requested_at (requested_at)
		) ENGINE=InnoDB $charset;";

		$sql[] = "CREATE TABLE $capacity (
			leave_date date NOT NULL,
			approved_count tinyint(3) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (leave_date),
			KEY approved_count (approved_count)
		) ENGINE=InnoDB $charset;";

		$sql[] = "CREATE TABLE $audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entity_type varchar(30) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			action_name varchar(50) NOT NULL,
			actor_id bigint(20) unsigned NOT NULL,
			payload_json longtext NOT NULL,
			prev_hash char(64) NOT NULL,
			entry_hash char(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entity (entity_type,entity_id),
			KEY created_at (created_at),
			UNIQUE KEY entry_hash (entry_hash)
		) ENGINE=InnoDB $charset;";

		$sql[] = "CREATE TABLE $documents (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_user_id bigint(20) unsigned NOT NULL,
			storage_name varchar(100) NOT NULL,
			original_name varchar(255) NOT NULL,
			mime_type varchar(100) NOT NULL,
			file_size bigint(20) unsigned NOT NULL,
			cipher varchar(30) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY storage_name (storage_name),
			KEY owner_user_id (owner_user_id)
		) ENGINE=InnoDB $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		$missing = ELM_DB::missing_tables();
		$missing_columns = ELM_DB::missing_request_snapshot_columns();
		$schema_errors = array_merge( $missing, array_map( static fn( string $column ): string => $requests . '.' . $column, $missing_columns ) );
		if ( $schema_errors ) {
			delete_transient( 'elm_schema_checked' );
			update_option( 'elm_schema_error', implode( ', ', $schema_errors ), false );
			ELM_DB::log_failure( 'Schema installation incomplete: ' . implode( ', ', $schema_errors ) );
			return false;
		}

		self::rebuild_capacity_cache();
		delete_option( 'elm_schema_error' );
		set_transient( 'elm_schema_checked', 1, 12 * HOUR_IN_SECONDS );
		update_option( 'elm_db_version', ELM_DB_VERSION, false );
		return true;
	}


	private static function rebuild_capacity_cache(): void {
		global $wpdb;
		$requests = ELM_DB::table( 'requests' );
		$request_days = ELM_DB::table( 'request_days' );
		$capacity = ELM_DB::table( 'capacity' );
		ELM_DB::begin();
		try {
			if ( false === $wpdb->query( "DELETE FROM $capacity" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				throw new RuntimeException( 'Capacity cache reset failed.' );
			}
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $capacity (leave_date,approved_count,updated_at)
					 SELECT d.leave_date,COUNT(DISTINCT r.id),%s
					 FROM $request_days d INNER JOIN $requests r ON r.id=d.request_id
					 WHERE r.status='approved' AND r.leave_type='annual' GROUP BY d.leave_date",
					ELM_DB::now()
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $inserted ) {
				throw new RuntimeException( 'Capacity cache rebuild failed.' );
			}
			ELM_DB::commit();
		} catch ( Throwable $e ) {
			ELM_DB::rollback();
			ELM_DB::log_failure( 'Rebuilding capacity cache', $e );
		}
	}

	private static function install_roles(): void {
		$employee_caps = array(
			'read'               => true,
			'elm_submit_leave'   => true,
			'elm_view_own_leave' => true,
		);
		$manager_caps = array_merge(
			$employee_caps,
			array(
				'elm_manage_leave'        => true,
				'elm_export_leave_reports' => true,
			)
		);
		$chief_caps = array_merge(
			$manager_caps,
			array(
				'elm_adjust_balances'       => true,
				'elm_view_medical_documents' => true,
				'elm_verify_audit'           => true,
			)
		);

		add_role( 'elm_employee', __( 'Punonjës', 'employee-leave-manager' ), $employee_caps );
		add_role( 'elm_manager', __( 'Mbikëqyrës i drejtpërdrejtë (bazë)', 'employee-leave-manager' ), $manager_caps );
		add_role( 'elm_chief', __( 'Mbikëqyrës i drejtpërdrejtë (i plotë)', 'employee-leave-manager' ), $chief_caps );
		// add_role() is a no-op once the slug already exists, so sites upgrading
		// from a version where both roles shared one label need an explicit rename.
		self::rename_role_label( 'elm_manager', __( 'Mbikëqyrës i drejtpërdrejtë (bazë)', 'employee-leave-manager' ) );
		self::rename_role_label( 'elm_chief', __( 'Mbikëqyrës i drejtpërdrejtë (i plotë)', 'employee-leave-manager' ) );

		// ELM capabilities belong only to dedicated ELM roles. The built-in Subscriber role
		// must never become an employee role because many WordPress sites use it for customers/members.
		$subscriber = get_role( 'subscriber' );
		if ( $subscriber ) {
			$subscriber->remove_cap( 'elm_submit_leave' );
			$subscriber->remove_cap( 'elm_view_own_leave' );
			$subscriber->remove_cap( 'elm_manage_leave' );
			$subscriber->remove_cap( 'elm_export_leave_reports' );
			$subscriber->remove_cap( 'elm_adjust_balances' );
			$subscriber->remove_cap( 'elm_view_medical_documents' );
			$subscriber->remove_cap( 'elm_verify_audit' );
		}

		foreach ( array( 'elm_employee' => $employee_caps, 'elm_manager' => $manager_caps, 'elm_chief' => $chief_caps ) as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $caps as $cap => $granted ) {
					if ( $granted ) {
						$role->add_cap( $cap );
					}
				}
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( array_keys( $chief_caps ) as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		self::migrate_known_subscriber_employees();
	}

	private static function rename_role_label( string $role_slug, string $label ): void {
		$wp_roles = wp_roles();
		if ( ! isset( $wp_roles->roles[ $role_slug ] ) || $wp_roles->roles[ $role_slug ]['name'] === $label ) {
			return;
		}
		$wp_roles->roles[ $role_slug ]['name'] = $label;
		if ( isset( $wp_roles->role_objects[ $role_slug ] ) ) {
			$wp_roles->role_objects[ $role_slug ]->name = $label;
		}
		update_option( $wp_roles->role_key, $wp_roles->roles );
	}

	/**
	 * Preserve access for subscribers who already have concrete ELM HR data while
	 * removing the unsafe blanket grant from the built-in Subscriber role.
	 */
	private static function migrate_known_subscriber_employees(): void {
		if ( get_option( 'elm_explicit_employee_migration_1_5_93', false ) ) {
			return;
		}
		$employee_role = get_role( 'elm_employee' );
		if ( ! $employee_role ) {
			return;
		}

		global $wpdb;
		$ids = array();
		if ( ELM_DB::schema_ready() ) {
			$sources = array(
				array( ELM_DB::table( 'requests' ), 'employee_id' ),
				array( ELM_DB::table( 'balances' ), 'user_id' ),
				array( ELM_DB::table( 'adjustments' ), 'user_id' ),
				array( ELM_DB::table( 'entitlement_changes' ), 'user_id' ),
				array( ELM_DB::table( 'medical_documents' ), 'owner_user_id' ),
			);
			foreach ( $sources as $source ) {
				$table  = (string) $source[0];
				$column = (string) $source[1];
				$found = $wpdb->get_col( "SELECT DISTINCT $column FROM $table WHERE $column > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
				$ids = array_merge( $ids, array_map( 'absint', is_array( $found ) ? $found : array() ) );
			}
		}

		$profile_users = get_users(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					'relation' => 'OR',
					array( 'key' => 'elm_position', 'compare' => 'EXISTS' ),
					array( 'key' => 'elm_sector', 'compare' => 'EXISTS' ),
				),
			)
		);
		$ids = array_merge( $ids, array_map( 'absint', $profile_users ) );
		$ids = array_values( array_unique( array_filter( $ids ) ) );

		foreach ( $ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user instanceof WP_User || user_can( $user, 'manage_options' ) || user_can( $user, 'elm_manage_leave' ) ) {
				continue;
			}
			if ( in_array( 'subscriber', (array) $user->roles, true ) && ! in_array( 'elm_employee', (array) $user->roles, true ) ) {
				$user->add_role( 'elm_employee' );
			}
		}

		update_option( 'elm_explicit_employee_migration_1_5_93', 1, false );
	}

	private static function install_defaults(): void {
		$defaults = array(
			'annual_entitlement' => 20.0,
			'period_one_limit'   => 0.0,
			'concurrency_limit'  => 2,
			'working_weekdays'   => array( 1, 2, 3, 4, 5 ),
			'holidays'           => array(),
			'holiday_names'      => array(),
			'movable_holidays'   => ELM_Policy::default_movable_holidays(),
			'max_upload_mb'      => 10,
			'frontend_only_enabled' => false,
			'portal_page_id'        => 0,
			'show_plus_one_when_empty' => false,
			'email_notifications_enabled' => true,
			'notification_cc_admin'       => false,
		);
		if ( false === get_option( 'elm_settings', false ) ) {
			add_option( 'elm_settings', $defaults, '', false );
		}
	}
}
