<?php
/**
 * wp-admin: certificate editor screen.
 *
 * @package DeftesePro
 *
 * @var string $cert_id  Certificate being edited, if any.
 * @var string $list_url Back-to-list link.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dp-wrap">
	<h1 class="wp-heading-inline">
		<?php
		if ( '' !== $cert_id ) {
			printf(
				/* translators: %s: registry number. */
				esc_html__( 'Ndrysho Dëftesën: %s', 'deftese-pro' ),
				esc_html( $cert_id )
			);
		} else {
			esc_html_e( 'Dëftesë e Re', 'deftese-pro' );
		}
		?>
	</h1>
	<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action"><?php esc_html_e( 'Kthehu te Lista', 'deftese-pro' ); ?></a>
	<hr class="wp-header-end">

	<div class="dp-app dp-app--embedded dp-app--editor">
		<?php DeftesePro\Editor::render( $cert_id, false ); ?>
	</div>
</div>
