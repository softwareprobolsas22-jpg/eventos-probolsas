<?php
/**
 * Punto de montaje de una interfaz construida con JS.
 *
 * Datos ($data):
 * - id (string) Identificador del contenedor.
 *
 * @package Probolsas\Eventos
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="<?php echo esc_attr( $data['id'] ); ?>" class="ep-mount" aria-busy="true">
	<p class="ep-mount__loading"><?php esc_html_e( 'Cargando…', 'eventos-probolsas' ); ?></p>
</div>
