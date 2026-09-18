<?php
/**
 * Front-end manager shell: header, tabs and the active view.
 *
 * @package DeftesePro
 *
 * @var string   $view         Active view key.
 * @var string   $cert_id      Certificate being edited.
 * @var WP_User  $current_user Signed-in user.
 * @var string   $logout_url   Logout link.
 * @var string   $list_url     List tab link.
 * @var string   $edit_url     Editor tab link.
 * @var string   $security_url Security tab link.
 */

defined( 'ABSPATH' ) || exit;

$dp_tabs = array(
	'list'     => array(
		'url'   => $list_url,
		'label' => __( 'Të Gjitha Dëftesat', 'deftese-pro' ),
		'icon'  => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
	),
	'edit'     => array(
		'url'   => $edit_url,
		'label' => __( 'Shto/Ndrysho Dëftesë', 'deftese-pro' ),
		'icon'  => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
	),
	'security' => array(
		'url'   => $security_url,
		'label' => __( 'Siguria', 'deftese-pro' ),
		'icon'  => '<path d="M12 3l8 4v5c0 4.5-3.2 8.3-8 9-4.8-.7-8-4.5-8-9V7Z"/>',
	),
);
?>
<div class="dp-app">
	<header class="dp-appbar">
		<div class="dp-appbar__identity">
			<h1 class="dp-appbar__title"><?php esc_html_e( 'Menaxheri i Dëftesave', 'deftese-pro' ); ?></h1>
			<p class="dp-appbar__meta">
				<?php esc_html_e( 'Komuna e Prishtinës', 'deftese-pro' ); ?>
				<span class="dp-appbar__dot" aria-hidden="true">&middot;</span>
				<?php
				printf(
					/* translators: %s: user display name. */
					esc_html__( 'Mirësevini, %s', 'deftese-pro' ),
					'<strong>' . esc_html( $current_user->display_name ) . '</strong>'
				);
				?>
			</p>
		</div>
		<a class="dp-btn dp-btn--ghost" href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Dil', 'deftese-pro' ); ?></a>
	</header>

	<nav class="dp-tabs" aria-label="<?php esc_attr_e( 'Seksionet e menaxherit', 'deftese-pro' ); ?>">
		<?php foreach ( $dp_tabs as $dp_key => $dp_tab ) : ?>
			<a class="dp-tab<?php echo $view === $dp_key ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( $dp_tab['url'] ); ?>"
				<?php echo $view === $dp_key ? 'aria-current="page"' : ''; ?>>
				<svg class="dp-tab__icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php echo $dp_tab['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG path data. ?></svg>
				<span><?php echo esc_html( $dp_tab['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="dp-app__body">
		<?php
		if ( 'list' === $view ) {
			DeftesePro\List_View::render( true );
		} elseif ( 'security' === $view ) {
			DeftesePro\Frontend::render_security();
		} else {
			DeftesePro\Editor::render( $cert_id, true );
		}
		?>
	</div>
</div>
