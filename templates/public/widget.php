<?php
/**
 * Contenedor de un widget de la intranet (shortcode). assets/src/js/pages/public.js lo monta.
 *
 * Datos ($data):
 * - widget (string) Nombre del widget: calendar o upcoming.
 * - props  (array)  Opciones del widget, ya saneadas. Viajan como JSON en data-ep-props.
 *
 * @package Probolsas\Eventos
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ep-public" data-ep-widget="<?php echo esc_attr( $data['widget'] ); ?>" data-ep-props="<?php echo esc_attr( (string) wp_json_encode( (object) $data['props'] ) ); ?>" aria-busy="true">
	<p class="ep-public__loading"><?php esc_html_e( 'Cargando…', 'eventos-probolsas' ); ?></p>
</div>
