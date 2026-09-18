<?php
/**
 * wp-admin: certificate list screen.
 *
 * @package DeftesePro
 *
 * @var string $new_url      Add-new link.
 * @var string $overview_url Overview link.
 * @var bool   $can_overview Whether the user may open the overview.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dp-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Dëftesë PRO — Të Gjitha Dëftesat', 'deftese-pro' ); ?></h1>
	<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( '+ Dëftesë e Re', 'deftese-pro' ); ?></a>
	<?php if ( $can_overview ) : ?>
		<a href="<?php echo esc_url( $overview_url ); ?>" class="page-title-action"><?php esc_html_e( 'Pasqyra Sipas Kategorive', 'deftese-pro' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<div class="dp-app dp-app--embedded">
		<?php DeftesePro\List_View::render( false ); ?>
	</div>
</div>
