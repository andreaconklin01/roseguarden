<?php
/**
 * Two-factor authentication settings panel.
 *
 * @package DeftesePro
 *
 * @var bool $enabled Whether 2FA is currently active for this user.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="dp-card dp-card--narrow">
	<header class="dp-card__head">
		<h2 class="dp-card__title"><?php esc_html_e( 'Autentikimi Dyfaktorësh (2FA)', 'deftese-pro' ); ?></h2>
		<p class="dp-card__hint"><?php esc_html_e( 'Shton një shtresë shtesë sigurie: përveç fjalëkalimit, do të kërkohet edhe një kod 6-shifror nga një aplikacion si Google Authenticator ose Authy.', 'deftese-pro' ); ?></p>
	</header>

	<div class="dp-card__body">
		<p class="dp-status dp-status--<?php echo $enabled ? 'on' : 'off'; ?>" id="deftese-2fa-status">
			<?php
			echo $enabled
				? esc_html__( 'Aktiv — llogaria jote është e mbrojtur me 2FA.', 'deftese-pro' )
				: esc_html__( 'Joaktiv — llogaria jote nuk përdor ende 2FA.', 'deftese-pro' );
			?>
		</p>

		<p class="dp-inline-msg" id="deftese-2fa-msg" role="status" aria-live="polite"></p>

		<?php if ( ! $enabled ) : ?>
			<div id="deftese-2fa-setup-start">
				<button type="button" class="dp-btn dp-btn--primary" id="btn-2fa-start"><?php esc_html_e( 'Aktivizo 2FA', 'deftese-pro' ); ?></button>
			</div>

			<div id="deftese-2fa-setup-details" hidden>
				<p class="dp-card__hint"><?php esc_html_e( 'Në aplikacionin tënd të autentikimit zgjidh "Fut çelësin manualisht" dhe ngjit këtë çelës:', 'deftese-pro' ); ?></p>
				<p class="dp-secret" id="deftese-2fa-secret"></p>
				<p class="dp-card__hint">
					<?php esc_html_e( 'Në telefon mund të prekësh edhe këtë link që hap direkt aplikacionin:', 'deftese-pro' ); ?>
					<a id="deftese-2fa-otpauth-link" href="#"><?php esc_html_e( 'hap në aplikacion', 'deftese-pro' ); ?></a>
				</p>

				<div class="dp-field">
					<label class="dp-label" for="deftese-2fa-confirm-code"><?php esc_html_e( 'Fut kodin 6-shifror për të konfirmuar', 'deftese-pro' ); ?></label>
					<input class="dp-input dp-input--code" type="text" id="deftese-2fa-confirm-code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" autocomplete="one-time-code">
				</div>

				<button type="button" class="dp-btn dp-btn--success" id="btn-2fa-confirm"><?php esc_html_e( 'Konfirmo dhe Aktivizo', 'deftese-pro' ); ?></button>
			</div>
		<?php else : ?>
			<div id="deftese-2fa-disable-block">
				<div class="dp-field">
					<label class="dp-label" for="deftese-2fa-disable-code"><?php esc_html_e( 'Për ta çaktivizuar, fut kodin aktual 6-shifror', 'deftese-pro' ); ?></label>
					<input class="dp-input dp-input--code" type="text" id="deftese-2fa-disable-code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" autocomplete="one-time-code">
				</div>

				<button type="button" class="dp-btn dp-btn--danger" id="btn-2fa-disable"><?php esc_html_e( 'Çaktivizo 2FA', 'deftese-pro' ); ?></button>
			</div>
		<?php endif; ?>
	</div>
</section>
