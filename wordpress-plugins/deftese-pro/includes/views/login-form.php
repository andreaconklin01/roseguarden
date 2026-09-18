<?php
/**
 * Front-end login form.
 *
 * @package DeftesePro
 *
 * @var string $status      Login status flag from the query string.
 * @var string $current_url URL to return to after posting.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="dp-auth">
	<form class="dp-auth__card" method="post">
		<div class="dp-auth__brand">
			<span class="dp-auth__mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<path d="M22 10 12 5 2 10l10 5 10-5Z" />
					<path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5" />
				</svg>
			</span>
			<h1 class="dp-auth__title"><?php esc_html_e( 'Menaxheri i Dëftesave', 'deftese-pro' ); ?></h1>
			<p class="dp-auth__subtitle"><?php esc_html_e( 'Komuna e Prishtinës', 'deftese-pro' ); ?></p>
		</div>

		<?php if ( 'failed' === $status ) : ?>
			<div class="dp-notice dp-notice--danger" role="alert">
				<span class="dp-notice__icon" aria-hidden="true"></span>
				<p class="dp-notice__text"><?php esc_html_e( 'Emri i përdoruesit ose fjalëkalimi është i pasaktë.', 'deftese-pro' ); ?></p>
			</div>
		<?php elseif ( 'locked' === $status ) : ?>
			<div class="dp-notice dp-notice--warning" role="alert">
				<span class="dp-notice__icon" aria-hidden="true"></span>
				<p class="dp-notice__text"><?php esc_html_e( 'Shumë përpjekje të dështuara. Provoni sërish pas 15 minutash.', 'deftese-pro' ); ?></p>
			</div>
		<?php endif; ?>

		<?php wp_nonce_field( 'deftese_frontend_login', 'deftese_login_nonce' ); ?>
		<input type="hidden" name="deftese_redirect" value="<?php echo esc_attr( $current_url ); ?>">

		<div class="dp-field">
			<label class="dp-label" for="deftese-username"><?php esc_html_e( 'Emri i përdoruesit ose Email', 'deftese-pro' ); ?></label>
			<input class="dp-input" type="text" id="deftese-username" name="deftese_username" required autocomplete="username" autocapitalize="none" spellcheck="false">
		</div>

		<div class="dp-field">
			<label class="dp-label" for="deftese-password"><?php esc_html_e( 'Fjalëkalimi', 'deftese-pro' ); ?></label>
			<input class="dp-input" type="password" id="deftese-password" name="deftese_password" required autocomplete="current-password">
		</div>

		<label class="dp-checkbox">
			<input type="checkbox" name="deftese_remember" value="1" checked>
			<span><?php esc_html_e( 'Më mbaj mend', 'deftese-pro' ); ?></span>
		</label>

		<button class="dp-btn dp-btn--primary dp-btn--block" type="submit"><?php esc_html_e( 'Kyçu', 'deftese-pro' ); ?></button>
	</form>
</div>
