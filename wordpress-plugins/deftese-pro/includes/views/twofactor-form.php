<?php
/**
 * One-time-code challenge.
 *
 * @package DeftesePro
 *
 * @var string $token     Pending-login token.
 * @var bool   $has_error Whether the previous attempt failed.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="dp-auth">
	<form class="dp-auth__card" method="post">
		<div class="dp-auth__brand">
			<span class="dp-auth__mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<rect x="4" y="10" width="16" height="11" rx="2" />
					<path d="M8 10V7a4 4 0 0 1 8 0v3" />
				</svg>
			</span>
			<h1 class="dp-auth__title"><?php esc_html_e( 'Verifikimi Dyfaktorësh', 'deftese-pro' ); ?></h1>
			<p class="dp-auth__subtitle"><?php esc_html_e( 'Fut kodin 6-shifror nga aplikacioni yt i autentikimit', 'deftese-pro' ); ?></p>
		</div>

		<?php if ( $has_error ) : ?>
			<div class="dp-notice dp-notice--danger" role="alert">
				<span class="dp-notice__icon" aria-hidden="true"></span>
				<p class="dp-notice__text"><?php esc_html_e( 'Kodi është i pasaktë ose ka skaduar. Provo sërish.', 'deftese-pro' ); ?></p>
			</div>
		<?php endif; ?>

		<?php wp_nonce_field( 'deftese_2fa_verify', 'deftese_2fa_nonce' ); ?>
		<input type="hidden" name="deftese_2fa_token" value="<?php echo esc_attr( $token ); ?>">

		<div class="dp-field">
			<label class="dp-label dp-u-visually-hidden" for="deftese-2fa-code"><?php esc_html_e( 'Kodi 6-shifror', 'deftese-pro' ); ?></label>
			<input class="dp-input dp-input--code" type="text" id="deftese-2fa-code" name="deftese_2fa_code"
				inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required autofocus placeholder="000000">
		</div>

		<button class="dp-btn dp-btn--primary dp-btn--block" type="submit"><?php esc_html_e( 'Verifiko', 'deftese-pro' ); ?></button>
	</form>
</div>
