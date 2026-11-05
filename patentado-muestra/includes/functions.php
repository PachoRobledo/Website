<?php
/**
 * Funciones compartidas: ajustes, progreso, quiz, video.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ajustes con valores por defecto.
 */
function pm_defaults() {
	return array(
		'program_name'   => 'Patentado',
		'class_title'    => 'Prioridad',
		'class_desc'     => 'Descubre cómo ordenar tu vida y tu liderazgo alrededor de lo que realmente importa.',
		'video_url'      => '',
		'pdf_url'        => '',
		'logo_url'       => '',
		'primary_color'  => '#1f3a5f',
		'accent_color'   => '#c9a227',
		'pass_score'     => 70,
		'other_classes'  => "Identidad\nPropósito\nCarácter\nRelaciones\nMayordomía\nLegado",
		'quiz'           => "¿Qué significa vivir con prioridad?\n* Ordenar mi vida según lo que realmente importa\n- Hacer todo lo que me piden\n- Trabajar más horas\n\n¿Cuál es el primer paso para establecer prioridades?\n- Comprar una agenda nueva\n* Identificar qué es lo más importante\n- Decir que sí a todo",
		'signer_name'    => '',
		'signer_title'   => 'Director(a) de Patentado',
		'email_subject'  => 'Tu acceso a la clase muestra de {programa}',
		'email_body'     => "Hola {nombre},\n\nTe invitamos a conocer {programa} a través de nuestra clase muestra \"{clase}\".\n\nIngresa aquí: {enlace}\nEmail: {email}\nContraseña: {password}\n\n¡Te esperamos!",
	);
}

function pm_settings() {
	$saved = get_option( 'pm_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), pm_defaults() );
}

function pm_setting( $key ) {
	$s = pm_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

function pm_page_url( $args = array() ) {
	$page_id = (int) get_option( 'pm_page_id' );
	$url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

function pm_is_sample( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user && $user->exists() && in_array( PM_ROLE, (array) $user->roles, true );
}

/**
 * ¿Puede este usuario ver la clase? (alumnos muestra y administradores para previsualizar)
 */
function pm_can_view( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return pm_is_sample( $user ) || user_can( $user, 'manage_options' );
}

/**
 * Progreso guardado en user meta.
 */
function pm_progress_defaults() {
	return array(
		'video_pct'      => 0,
		'video_segments' => '',
		'pdf_viewed'     => 0,
		'pdf_downloaded' => 0,
		'quiz_score'     => null,
		'quiz_attempts'  => 0,
		'quiz_passed'    => 0,
		'completed_at'   => 0,
		'cert_code'      => '',
		'started_at'     => 0,
		'last_activity'  => 0,
	);
}

function pm_get_progress( $user_id ) {
	$p = get_user_meta( $user_id, 'pm_progress', true );
	return wp_parse_args( is_array( $p ) ? $p : array(), pm_progress_defaults() );
}

function pm_update_progress( $user_id, $changes ) {
	$p = array_merge( pm_get_progress( $user_id ), $changes );
	if ( ! $p['started_at'] ) {
		$p['started_at'] = time();
	}
	$p['last_activity'] = time();
	update_user_meta( $user_id, 'pm_progress', $p );
	return $p;
}

/**
 * Porcentaje global de avance (video 40%, PDF 20%, quiz 40%).
 */
function pm_overall_pct( $p ) {
	$pct  = min( 100, (int) $p['video_pct'] ) * 0.4;
	$pct += ( $p['pdf_viewed'] || $p['pdf_downloaded'] ) ? 20 : 0;
	$pct += $p['quiz_passed'] ? 40 : 0;
	return $p['completed_at'] ? 100 : (int) round( $pct );
}

/**
 * Formato del quiz (en Ajustes):
 *   Pregunta
 *   * respuesta correcta
 *   - respuesta incorrecta
 *   (línea en blanco entre preguntas)
 */
function pm_parse_quiz( $text = null ) {
	$text      = null === $text ? pm_setting( 'quiz' ) : $text;
	$blocks    = preg_split( "/\R\s*\R/", trim( (string) $text ) );
	$questions = array();
	foreach ( $blocks as $block ) {
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $block ) ), 'strlen' ) );
		if ( count( $lines ) < 2 ) {
			continue;
		}
		$q = array( 'question' => '', 'options' => array(), 'correct' => array() );
		foreach ( $lines as $line ) {
			$first = substr( $line, 0, 1 );
			if ( '*' === $first || '-' === $first ) {
				if ( '*' === $first ) {
					$q['correct'][] = count( $q['options'] );
				}
				$q['options'][] = trim( substr( $line, 1 ) );
			} elseif ( '' === $q['question'] ) {
				$q['question'] = $line;
			} else {
				$q['question'] .= ' ' . $line;
			}
		}
		if ( $q['question'] && count( $q['options'] ) >= 2 && $q['correct'] ) {
			$questions[] = $q;
		}
	}
	return $questions;
}

/**
 * Detecta el tipo de video a partir de la URL.
 */
function pm_video_info( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return array( 'type' => 'none', 'id' => '', 'url' => '' );
	}
	if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m ) ) {
		return array( 'type' => 'youtube', 'id' => $m[1], 'url' => $url );
	}
	if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)(?:/([a-z0-9]+))?~i', $url, $m ) ) {
		$embed = 'https://player.vimeo.com/video/' . $m[1];
		if ( ! empty( $m[2] ) ) {
			$embed .= '?h=' . $m[2];
		} elseif ( preg_match( '~[?&]h=([a-z0-9]+)~i', $url, $h ) ) {
			$embed .= '?h=' . $h[1];
		}
		return array( 'type' => 'vimeo', 'id' => $m[1], 'url' => $embed );
	}
	return array( 'type' => 'file', 'id' => '', 'url' => $url );
}

/**
 * Crea un alumno muestra. Devuelve array( user_id, password ) o WP_Error.
 */
function pm_create_student( $email, $name, $kind = '', $org = '' ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'pm_email', 'El email no es válido.' );
	}
	if ( email_exists( $email ) || username_exists( $email ) ) {
		return new WP_Error( 'pm_exists', 'Ya existe un usuario con ese email.' );
	}
	$password = pm_generate_password();
	$parts    = preg_split( '/\s+/', trim( $name ), 2 );
	$user_id  = wp_insert_user(
		array(
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $name ? $name : $email,
			'first_name'   => isset( $parts[0] ) ? $parts[0] : '',
			'last_name'    => isset( $parts[1] ) ? $parts[1] : '',
			'role'         => PM_ROLE,
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}
	update_user_meta( $user_id, 'pm_kind', sanitize_text_field( $kind ) );
	update_user_meta( $user_id, 'pm_org', sanitize_text_field( $org ) );
	update_user_meta( $user_id, 'pm_created_by', get_current_user_id() );
	update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
	return array( $user_id, $password );
}

/**
 * Contraseña fácil de leer y dictar (sin caracteres ambiguos).
 */
function pm_generate_password( $length = 10 ) {
	$chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$out   = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	return $out;
}

/**
 * Envía el email de bienvenida con las credenciales.
 */
function pm_send_credentials( $user_id, $password ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	$replace = array(
		'{nombre}'   => $user->first_name ? $user->first_name : $user->display_name,
		'{email}'    => $user->user_email,
		'{password}' => $password,
		'{enlace}'   => pm_page_url(),
		'{programa}' => pm_setting( 'program_name' ),
		'{clase}'    => pm_setting( 'class_title' ),
	);
	$subject = strtr( pm_setting( 'email_subject' ), $replace );
	$body    = strtr( pm_setting( 'email_body' ), $replace );
	return wp_mail( $user->user_email, $subject, $body );
}

function pm_format_date( $ts ) {
	return $ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '—';
}
