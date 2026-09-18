<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ELM_Employee_Profile {
	private const POSITION_META = 'elm_position';
	private const SECTOR_META   = 'elm_sector';

	public static function register(): void {
		add_action( 'show_user_profile', array( self::class, 'render_fields' ) );
		add_action( 'edit_user_profile', array( self::class, 'render_fields' ) );
		add_action( 'personal_options_update', array( self::class, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( self::class, 'save_fields' ) );
	}

	public static function position( int $user_id ): string {
		return self::value( $user_id, self::POSITION_META, array( 'position', 'job_title', 'job_position' ) );
	}

	public static function sector( int $user_id ): string {
		return self::value( $user_id, self::SECTOR_META, array( 'sector', 'department', 'unit', 'organization_unit' ) );
	}


	public static function update_for_manager( int $user_id, mixed $position, mixed $sector, int $actor_id = 0 ): array|WP_Error {
		if ( ! current_user_can( 'elm_manage_leave' ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Nuk keni leje t\'i redaktoni të dhënat e punonjësit.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'elm_invalid_employee', __( 'Zgjidhni një punonjës të vlefshëm.', 'employee-leave-manager' ), array( 'status' => 400 ) );
		}
		$actor_id = $actor_id;
		if ( ! user_can( $actor_id, 'manage_options' ) && ! ELM_Chief_Access::can_manage_employee( $actor_id, $user_id ) ) {
			return new WP_Error( 'elm_forbidden', __( 'Ky punonjës nuk është i caktuar nën mbikëqyrjen tuaj të drejtpërdrejtë.', 'employee-leave-manager' ), array( 'status' => 403 ) );
		}
		$before = array(
			'position' => self::position( $user_id ),
			'sector'   => self::sector( $user_id ),
		);
		$after = array(
			'position' => self::sanitize_value( $position ),
			'sector'   => self::sanitize_value( $sector ),
		);
		update_user_meta( $user_id, self::POSITION_META, $after['position'] );
		update_user_meta( $user_id, self::SECTOR_META, $after['sector'] );
		if ( ELM_DB::schema_ready() ) {
			ELM_DB::begin();
			$audit = ELM_Audit::append( 'employee_profile', $user_id, 'updated', $actor_id ?: get_current_user_id(), array( 'before' => $before, 'after' => $after, 'source' => 'frontend' ) );
			if ( is_wp_error( $audit ) ) {
				ELM_DB::rollback();
				update_user_meta( $user_id, self::POSITION_META, $before['position'] );
				update_user_meta( $user_id, self::SECTOR_META, $before['sector'] );
				return $audit;
			}
			ELM_DB::commit();
		}
		return array(
			'id'       => $user_id,
			'name'     => $user->display_name,
			'email'    => $user->user_email,
			'position' => $after['position'],
			'sector'   => $after['sector'],
		);
	}

	public static function render_fields( WP_User $user ): void {
		if ( ! self::can_manage( $user->ID ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Të dhënat e punonjësit për menaxhimin e pushimeve', 'employee-leave-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="elm_position"><?php esc_html_e( 'Pozita', 'employee-leave-manager' ); ?></label></th>
				<td>
					<input type="text" name="elm_position" id="elm_position" value="<?php echo esc_attr( self::position( $user->ID ) ); ?>" class="regular-text" maxlength="255">
					<p class="description"><?php esc_html_e( 'Pozita zyrtare e punonjësit që shfaqet në raportet PDF të kërkesave për pushim.', 'employee-leave-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="elm_sector"><?php esc_html_e( 'Sektori/Njësia', 'employee-leave-manager' ); ?></label></th>
				<td>
					<input type="text" name="elm_sector" id="elm_sector" value="<?php echo esc_attr( self::sector( $user->ID ) ); ?>" class="regular-text" maxlength="255">
					<p class="description"><?php esc_html_e( 'Sektori ose njësia organizative zyrtare që shfaqet në raportet PDF të kërkesave për pushim.', 'employee-leave-manager' ); ?></p>
				</td>
			</tr>
		</table>
		<?php wp_nonce_field( 'elm_save_employee_details_' . $user->ID, 'elm_employee_details_nonce' ); ?>
		<?php
	}

	public static function save_fields( int $user_id ): void {
		if ( ! self::can_manage( $user_id ) ) {
			return;
		}
		$nonce = isset( $_POST['elm_employee_details_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['elm_employee_details_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'elm_save_employee_details_' . $user_id ) ) {
			return;
		}

		$position = isset( $_POST['elm_position'] ) ? self::sanitize_value( wp_unslash( $_POST['elm_position'] ) ) : '';
		$sector   = isset( $_POST['elm_sector'] ) ? self::sanitize_value( wp_unslash( $_POST['elm_sector'] ) ) : '';
		update_user_meta( $user_id, self::POSITION_META, $position );
		update_user_meta( $user_id, self::SECTOR_META, $sector );
	}

	private static function can_manage( int $user_id ): bool {
		return ( current_user_can( 'elm_manage_leave' ) || current_user_can( 'manage_options' ) )
			&& current_user_can( 'edit_user', $user_id );
	}

	private static function value( int $user_id, string $primary_key, array $fallback_keys ): string {
		if ( metadata_exists( 'user', $user_id, $primary_key ) ) {
			return self::sanitize_value( get_user_meta( $user_id, $primary_key, true ) );
		}
		foreach ( $fallback_keys as $key ) {
			$value = self::sanitize_value( get_user_meta( $user_id, $key, true ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	private static function sanitize_value( mixed $value ): string {
		$value = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 255 ) : substr( $value, 0, 255 );
	}
}
