<?php
/**
 * Área de administración: lista de Alumnos Muestra, progreso y ajustes.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'pm_admin_menu' );
function pm_admin_menu() {
	add_menu_page( 'Alumnos Muestra', 'Alumnos Muestra', 'manage_options', 'pm-alumnos', 'pm_admin_students_page', 'dashicons-welcome-learn-more', 26 );
	add_submenu_page( 'pm-alumnos', 'Alumnos Muestra', 'Alumnos y progreso', 'manage_options', 'pm-alumnos', 'pm_admin_students_page' );
	add_submenu_page( 'pm-alumnos', 'Ajustes de la clase muestra', 'Ajustes de la clase', 'manage_options', 'pm-ajustes', 'pm_admin_settings_page' );
}

add_action( 'admin_enqueue_scripts', 'pm_admin_assets' );
function pm_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'pm-' ) ) {
		return;
	}
	wp_enqueue_style( 'pm-admin', PM_URL . 'assets/admin.css', array(), PM_VERSION );
	if ( false !== strpos( $hook, 'pm-ajustes' ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'pm-admin', PM_URL . 'assets/admin.js', array( 'jquery' ), PM_VERSION, true );
	}
}

function pm_admin_redirect( $args ) {
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=pm-alumnos' ) ) );
	exit;
}

/* -------------------------------------------------------------------------
 * Acciones
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_pm_add_student', 'pm_handle_add_student' );
function pm_handle_add_student() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'pm_add_student' );

	$result = pm_create_student(
		wp_unslash( $_POST['email'] ?? '' ),
		sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
		wp_unslash( $_POST['kind'] ?? '' ),
		wp_unslash( $_POST['org'] ?? '' )
	);
	if ( is_wp_error( $result ) ) {
		pm_admin_redirect( array( 'pm_error' => rawurlencode( $result->get_error_message() ) ) );
	}
	list( $user_id, $password ) = $result;

	$sent = ! empty( $_POST['send_email'] ) ? pm_send_credentials( $user_id, $password ) : null;
	pm_store_flash( $user_id, $password, $sent );
	pm_admin_redirect( array( 'pm_done' => 'added' ) );
}

add_action( 'admin_post_pm_student_action', 'pm_handle_student_action' );
function pm_handle_student_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	$user_id = absint( $_GET['user'] ?? 0 );
	$do      = sanitize_key( $_GET['do'] ?? '' );
	check_admin_referer( 'pm_student_' . $do . '_' . $user_id );

	$user = get_userdata( $user_id );
	if ( ! $user || ! pm_is_sample( $user ) ) {
		pm_admin_redirect( array( 'pm_error' => rawurlencode( 'Alumno no encontrado.' ) ) );
	}

	switch ( $do ) {
		case 'password':
		case 'resend':
			$password = pm_generate_password();
			wp_set_password( $password, $user_id );
			$sent = 'resend' === $do ? pm_send_credentials( $user_id, $password ) : null;
			pm_store_flash( $user_id, $password, $sent );
			pm_admin_redirect( array( 'pm_done' => 'password' ) );
			break;
		case 'reset':
			delete_user_meta( $user_id, 'pm_progress' );
			pm_admin_redirect( array( 'pm_done' => 'reset' ) );
			break;
		case 'delete':
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			pm_admin_redirect( array( 'pm_done' => 'deleted' ) );
			break;
	}
	pm_admin_redirect( array() );
}

/**
 * Guarda la contraseña por unos minutos para mostrarla UNA vez al administrador.
 */
function pm_store_flash( $user_id, $password, $sent ) {
	set_transient(
		'pm_flash_' . get_current_user_id(),
		array( 'user_id' => $user_id, 'password' => $password, 'sent' => $sent ),
		10 * MINUTE_IN_SECONDS
	);
}

add_action( 'admin_post_pm_export', 'pm_handle_export' );
function pm_handle_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'pm_export' );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=alumnos-muestra-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'Nombre', 'Email', 'Tipo', 'Iglesia / Organización', 'Creado', 'Último ingreso', 'Avance %', 'Video %', 'PDF visto', 'PDF descargado', 'Quiz %', 'Intentos', 'Completado', 'Código certificado' ) );
	foreach ( pm_get_students() as $u ) {
		$p = pm_get_progress( $u->ID );
		fputcsv(
			$out,
			array(
				$u->display_name,
				$u->user_email,
				get_user_meta( $u->ID, 'pm_kind', true ),
				get_user_meta( $u->ID, 'pm_org', true ),
				$u->user_registered,
				pm_format_date( (int) get_user_meta( $u->ID, 'pm_last_login', true ) ),
				pm_overall_pct( $p ),
				(int) $p['video_pct'],
				$p['pdf_viewed'] ? 'Sí' : 'No',
				$p['pdf_downloaded'] ? 'Sí' : 'No',
				null === $p['quiz_score'] ? '' : (int) $p['quiz_score'],
				(int) $p['quiz_attempts'],
				pm_format_date( (int) $p['completed_at'] ),
				$p['cert_code'],
			)
		);
	}
	fclose( $out );
	exit;
}

function pm_get_students() {
	return get_users(
		array(
			'role'    => PM_ROLE,
			'orderby' => 'registered',
			'order'   => 'DESC',
		)
	);
}

/* -------------------------------------------------------------------------
 * Página: Alumnos y progreso
 * ---------------------------------------------------------------------- */

function pm_admin_students_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$students = pm_get_students();
	$total    = count( $students );
	$started  = 0;
	$done     = 0;
	foreach ( $students as $u ) {
		$p = pm_get_progress( $u->ID );
		if ( $p['started_at'] ) {
			$started++;
		}
		if ( $p['completed_at'] ) {
			$done++;
		}
	}
	$flash = get_transient( 'pm_flash_' . get_current_user_id() );
	if ( $flash ) {
		delete_transient( 'pm_flash_' . get_current_user_id() );
	}
	$messages = array(
		'added'    => 'Alumno muestra creado.',
		'password' => 'Nueva contraseña generada.',
		'reset'    => 'Progreso reiniciado.',
		'deleted'  => 'Alumno eliminado.',
	);
	?>
	<div class="wrap pm-admin">
		<h1 class="wp-heading-inline">Alumnos Muestra</h1>
		<a class="page-title-action" href="<?php echo esc_url( pm_page_url() ); ?>" target="_blank">Ver la clase muestra ↗</a>
		<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pm_export' ), 'pm_export' ) ); ?>">Exportar CSV</a>
		<hr class="wp-header-end">

		<?php if ( ! empty( $_GET['pm_error'] ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( wp_unslash( rawurldecode( $_GET['pm_error'] ) ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['pm_done'] ) && isset( $messages[ $_GET['pm_done'] ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $messages[ $_GET['pm_done'] ] ); ?></p></div>
		<?php endif; ?>

		<?php
		if ( $flash ) :
			$fu = get_userdata( $flash['user_id'] );
			if ( $fu ) :
				?>
			<div class="pm-credentials">
				<h2>Credenciales de acceso de <?php echo esc_html( $fu->display_name ); ?></h2>
				<p class="pm-muted">Copia estos datos ahora: por seguridad la contraseña no se vuelve a mostrar.
					<?php
					if ( true === $flash['sent'] ) {
						echo '<strong>También se enviaron por email.</strong>';
					} elseif ( false === $flash['sent'] ) {
						echo '<strong class="pm-warn">No se pudo enviar el email: compártelos manualmente.</strong>';
					}
					?>
				</p>
				<textarea readonly rows="4" onclick="this.select()">Enlace: <?php echo esc_html( pm_page_url() ); ?>&#10;Email: <?php echo esc_html( $fu->user_email ); ?>&#10;Contraseña: <?php echo esc_html( $flash['password'] ); ?></textarea>
			</div>
				<?php
			endif;
		endif;
		?>

		<div class="pm-stats">
			<div><strong><?php echo (int) $total; ?></strong><span>Alumnos muestra</span></div>
			<div><strong><?php echo (int) $started; ?></strong><span>Han empezado</span></div>
			<div><strong><?php echo (int) $done; ?></strong><span>Completaron la clase</span></div>
			<div><strong><?php echo $total ? (int) round( $done / $total * 100 ) : 0; ?>%</strong><span>Tasa de finalización</span></div>
		</div>

		<div class="pm-card">
			<h2>Agregar alumno muestra</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pm-add-form">
				<input type="hidden" name="action" value="pm_add_student">
				<?php wp_nonce_field( 'pm_add_student' ); ?>
				<label>Nombre completo<input type="text" name="name" required></label>
				<label>Email<input type="email" name="email" required></label>
				<label>Tipo
					<select name="kind">
						<option>Pastor</option>
						<option>Coach</option>
						<option>Líder</option>
						<option>Otro</option>
					</select>
				</label>
				<label>Iglesia / Organización<input type="text" name="org"></label>
				<label class="pm-check"><input type="checkbox" name="send_email" value="1" checked> Enviarle sus credenciales por email</label>
				<button class="button button-primary">Crear y generar contraseña</button>
			</form>
		</div>

		<table class="widefat striped pm-table">
			<thead>
				<tr>
					<th>Alumno</th>
					<th>Tipo</th>
					<th>Último ingreso</th>
					<th>Avance</th>
					<th>Video</th>
					<th>PDF</th>
					<th>Quiz</th>
					<th>Estado</th>
					<th>Acciones</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $students ) : ?>
				<tr><td colspan="9">Todavía no hay alumnos muestra. Agrega el primero arriba.</td></tr>
			<?php endif; ?>
			<?php
			foreach ( $students as $u ) :
				$p       = pm_get_progress( $u->ID );
				$overall = pm_overall_pct( $p );
				$org     = get_user_meta( $u->ID, 'pm_org', true );
				$last    = (int) get_user_meta( $u->ID, 'pm_last_login', true );
				$link    = function ( $do ) use ( $u ) {
					return wp_nonce_url( admin_url( 'admin-post.php?action=pm_student_action&do=' . $do . '&user=' . $u->ID ), 'pm_student_' . $do . '_' . $u->ID );
				};
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $u->display_name ); ?></strong><br>
						<a href="mailto:<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( $u->user_email ); ?></a>
						<?php if ( $org ) : ?><br><span class="pm-muted"><?php echo esc_html( $org ); ?></span><?php endif; ?>
					</td>
					<td><?php echo esc_html( get_user_meta( $u->ID, 'pm_kind', true ) ); ?></td>
					<td><?php echo esc_html( $last ? pm_format_date( $last ) : 'Nunca' ); ?></td>
					<td>
						<div class="pm-bar"><span style="width:<?php echo (int) $overall; ?>%"></span></div>
						<small><?php echo (int) $overall; ?>%</small>
					</td>
					<td><?php echo (int) $p['video_pct']; ?>% visto</td>
					<td>
						<?php echo $p['pdf_viewed'] ? '✓ Visto' : '— '; ?>
						<?php echo $p['pdf_downloaded'] ? '<br>✓ Descargado' : ''; ?>
					</td>
					<td>
						<?php
						if ( null === $p['quiz_score'] ) {
							echo '—';
						} else {
							echo (int) $p['quiz_score'] . '%';
							echo '<br><small>' . (int) $p['quiz_attempts'] . ' intento(s)</small>';
						}
						?>
					</td>
					<td>
						<?php if ( $p['completed_at'] ) : ?>
							<span class="pm-pill pm-pill-done">Completado</span><br><small><?php echo esc_html( pm_format_date( (int) $p['completed_at'] ) ); ?></small>
						<?php elseif ( $p['started_at'] ) : ?>
							<span class="pm-pill pm-pill-progress">En progreso</span>
						<?php else : ?>
							<span class="pm-pill">Sin empezar</span>
						<?php endif; ?>
					</td>
					<td class="pm-actions">
						<a href="<?php echo esc_url( $link( 'resend' ) ); ?>" onclick="return confirm('Se generará una contraseña nueva y se enviará por email. ¿Continuar?')">Reenviar acceso</a>
						<a href="<?php echo esc_url( $link( 'password' ) ); ?>" onclick="return confirm('¿Generar una contraseña nueva? La anterior dejará de funcionar.')">Nueva contraseña</a>
						<a href="<?php echo esc_url( $link( 'reset' ) ); ?>" onclick="return confirm('¿Borrar todo el progreso de este alumno?')">Reiniciar progreso</a>
						<a class="pm-danger" href="<?php echo esc_url( $link( 'delete' ) ); ?>" onclick="return confirm('¿Eliminar este alumno muestra?')">Eliminar</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Página: Ajustes
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_pm_save_settings', 'pm_handle_save_settings' );
function pm_handle_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'pm_save_settings' );
	$in  = wp_unslash( $_POST['pm'] ?? array() );
	$out = array();
	foreach ( pm_defaults() as $key => $default ) {
		$val = isset( $in[ $key ] ) ? $in[ $key ] : '';
		switch ( $key ) {
			case 'video_url':
			case 'pdf_url':
			case 'logo_url':
				$out[ $key ] = esc_url_raw( trim( $val ) );
				break;
			case 'primary_color':
			case 'accent_color':
			case 'button_color':
				$out[ $key ] = sanitize_hex_color( $val ) ? sanitize_hex_color( $val ) : $default;
				break;
			case 'pass_score':
				$out[ $key ] = max( 0, min( 100, (int) $val ) );
				break;
			case 'quiz':
			case 'other_classes':
			case 'email_body':
			case 'class_desc':
				$out[ $key ] = sanitize_textarea_field( $val );
				break;
			default:
				$out[ $key ] = sanitize_text_field( $val );
		}
	}
	update_option( 'pm_settings', $out );
	wp_safe_redirect( admin_url( 'admin.php?page=pm-ajustes&saved=1' ) );
	exit;
}

function pm_admin_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s         = pm_settings();
	$questions = pm_parse_quiz( $s['quiz'] );
	$field     = function ( $key, $label, $type = 'text', $help = '' ) use ( $s ) {
		echo '<tr><th><label for="pm_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="pm_' . esc_attr( $key ) . '" name="pm[' . esc_attr( $key ) . ']" rows="' . ( 'quiz' === $key ? 16 : 5 ) . '" class="large-text code">' . esc_textarea( $s[ $key ] ) . '</textarea>';
		} elseif ( 'media' === $type ) {
			echo '<input type="url" id="pm_' . esc_attr( $key ) . '" name="pm[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ) . '" class="regular-text"> ';
			echo '<button type="button" class="button pm-media" data-target="pm_' . esc_attr( $key ) . '">Elegir de la biblioteca</button>';
		} else {
			echo '<input type="' . esc_attr( $type ) . '" id="pm_' . esc_attr( $key ) . '" name="pm[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ) . '" class="' . ( 'number' === $type || 'color' === $type ? 'small-text' : 'regular-text' ) . '">';
		}
		if ( $help ) {
			echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	?>
	<div class="wrap pm-admin">
		<h1>Ajustes de la clase muestra</h1>
		<?php if ( ! empty( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Ajustes guardados.</p></div>
		<?php endif; ?>
		<p>La clase se muestra en la página <a href="<?php echo esc_url( pm_page_url() ); ?>" target="_blank"><?php echo esc_html( pm_page_url() ); ?></a> (shortcode <code>[patentado_muestra]</code>).</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pm_save_settings">
			<?php wp_nonce_field( 'pm_save_settings' ); ?>

			<h2>La clase</h2>
			<table class="form-table">
				<?php
				$field( 'program_name', 'Nombre del programa' );
				$field( 'class_title', 'Nombre de la clase' );
				$field( 'class_desc', 'Descripción', 'textarea' );
				$field( 'video_url', 'Video de la clase', 'media', 'Enlace de YouTube, Vimeo o un archivo MP4. El alumno muestra podrá adelantar y retroceder libremente.' );
				$field( 'pdf_url', 'PDF de la clase', 'media', 'Sube el PDF a la biblioteca de medios o pega su enlace.' );
				$field( 'other_classes', 'Otras clases del currículo', 'textarea', 'Una por línea. Aparecen bloqueadas en el panel del alumno muestra para que vea el currículo completo.' );
				?>
			</table>

			<h2>Quiz</h2>
			<table class="form-table">
				<?php
				$field( 'quiz', 'Preguntas', 'textarea', 'Escribe cada pregunta en una línea y debajo sus respuestas. Marca la correcta con <code>*</code> y las incorrectas con <code>-</code>. Deja una línea en blanco entre preguntas.<br><strong>' . count( $questions ) . ' pregunta(s) válidas detectadas.</strong>' );
				$field( 'pass_score', 'Puntaje para aprobar (%)', 'number' );
				?>
			</table>

			<h2>Certificado y apariencia</h2>
			<table class="form-table">
				<?php
				$field( 'logo_url', 'Logo', 'media' );
				$field( 'primary_color', 'Color principal', 'color' );
				$field( 'accent_color', 'Color de acento', 'color' );
				$field( 'button_color', 'Color de botones', 'color' );
				$field( 'signer_name', 'Firma (nombre)' );
				$field( 'signer_title', 'Firma (cargo)' );
				?>
			</table>

			<h2>Email de bienvenida</h2>
			<table class="form-table">
				<?php
				$field( 'email_subject', 'Asunto' );
				$field( 'email_body', 'Mensaje', 'textarea', 'Variables: <code>{nombre}</code> <code>{email}</code> <code>{password}</code> <code>{enlace}</code> <code>{programa}</code> <code>{clase}</code>' );
				?>
			</table>

			<?php submit_button( 'Guardar ajustes' ); ?>
		</form>
	</div>
	<?php
}
