<?php
/**
 * Plugin Name:       Patentado – Clase Muestra
 * Description:       Clase de muestra "Prioridad" para pastores, coaches y líderes: panel de alumno, video sin restricciones, PDF descargable, quiz, felicitación final y certificado con marca de agua SAMPLE. Incluye la lista de "Alumnos Muestra" con su progreso.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Patentado
 * Text Domain:       patentado-muestra
 */

defined( 'ABSPATH' ) || exit;

define( 'PM_VERSION', '1.0.0' );
define( 'PM_FILE', __FILE__ );
define( 'PM_DIR', plugin_dir_path( __FILE__ ) );
define( 'PM_URL', plugin_dir_url( __FILE__ ) );
define( 'PM_ROLE', 'alumno_muestra' );

require_once PM_DIR . 'includes/functions.php';
require_once PM_DIR . 'includes/admin.php';
require_once PM_DIR . 'includes/frontend.php';

/**
 * Activación: crea el rol y la página de la clase muestra.
 */
function pm_activate() {
	add_role(
		PM_ROLE,
		'Alumno Muestra',
		array( 'read' => true )
	);

	$page_id = (int) get_option( 'pm_page_id' );
	if ( ! $page_id || ! get_post( $page_id ) ) {
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'Clase Muestra',
				'post_name'    => 'clase-muestra',
				'post_content' => '[patentado_muestra]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'pm_page_id', (int) $page_id );
		}
	}
}
register_activation_hook( __FILE__, 'pm_activate' );
