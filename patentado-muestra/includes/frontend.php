<?php
/**
 * Parte pública: login, panel del alumno, clase, quiz, felicitación y certificado.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Acceso y restricciones del rol
 * ---------------------------------------------------------------------- */

// Los alumnos muestra no entran al escritorio de WordPress.
add_action( 'admin_init', 'pm_block_admin' );
function pm_block_admin() {
	if ( wp_doing_ajax() || ! pm_is_sample() || current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_safe_redirect( pm_page_url() );
	exit;
}

add_filter( 'show_admin_bar', 'pm_hide_admin_bar' );
function pm_hide_admin_bar( $show ) {
	return pm_is_sample() ? false : $show;
}

add_filter( 'login_redirect', 'pm_login_redirect', 10, 3 );
function pm_login_redirect( $redirect_to, $requested, $user ) {
	return ( $user instanceof WP_User && pm_is_sample( $user ) ) ? pm_page_url() : $redirect_to;
}

add_action( 'wp_login', 'pm_track_login', 10, 2 );
function pm_track_login( $login, $user ) {
	if ( pm_is_sample( $user ) ) {
		update_user_meta( $user->ID, 'pm_last_login', time() );
	}
}

// Formulario propio de ingreso con email + contraseña.
add_action( 'init', 'pm_handle_login' );
function pm_handle_login() {
	if ( empty( $_POST['pm_login'] ) ) {
		return;
	}
	if ( ! isset( $_POST['_pmnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_pmnonce'] ), 'pm_login' ) ) {
		wp_safe_redirect( pm_page_url( array( 'pm_login_error' => 'expired' ) ) );
		exit;
	}
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$user  = get_user_by( 'email', $email );
	if ( ! $user ) {
		wp_safe_redirect( pm_page_url( array( 'pm_login_error' => 'invalid' ) ) );
		exit;
	}
	$signed = wp_signon(
		array(
			'user_login'    => $user->user_login,
			'user_password' => (string) wp_unslash( $_POST['password'] ?? '' ),
			'remember'      => true,
		),
		is_ssl()
	);
	if ( is_wp_error( $signed ) ) {
		wp_safe_redirect( pm_page_url( array( 'pm_login_error' => 'invalid' ) ) );
		exit;
	}
	wp_safe_redirect( pm_page_url() );
	exit;
}

/* -------------------------------------------------------------------------
 * Recursos
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'pm_register_assets' );
function pm_register_assets() {
	wp_register_style( 'pm-front', PM_URL . 'assets/muestra.css', array(), PM_VERSION );
	wp_register_script( 'pm-vimeo', 'https://player.vimeo.com/api/player.js', array(), null, true );
	wp_register_script( 'pm-front', PM_URL . 'assets/muestra.js', array(), PM_VERSION, true );
}

function pm_inline_colors() {
	$s = pm_settings();
	return sprintf(
		'.pm-app{--pm-primary:%1$s;--pm-accent:%2$s;}',
		esc_attr( $s['primary_color'] ),
		esc_attr( $s['accent_color'] )
	);
}

/* -------------------------------------------------------------------------
 * Shortcode
 * ---------------------------------------------------------------------- */

add_shortcode( 'patentado_muestra', 'pm_shortcode' );
function pm_shortcode() {
	wp_enqueue_style( 'pm-front' );
	wp_add_inline_style( 'pm-front', pm_inline_colors() );

	$user = wp_get_current_user();
	if ( ! $user->exists() ) {
		return pm_render_login();
	}
	if ( ! pm_can_view( $user ) ) {
		return '<div class="pm-app"><div class="pm-card pm-center"><p>Esta clase muestra es solo para invitados. Si recibiste una invitación, cierra sesión e ingresa con el email al que te llegó.</p><p><a class="pm-btn" href="' . esc_url( wp_logout_url( pm_page_url() ) ) . '">Cerrar sesión</a></p></div></div>';
	}

	$view = isset( $_GET['pm_vista'] ) ? sanitize_key( $_GET['pm_vista'] ) : 'panel';
	return 'clase' === $view ? pm_render_class( $user ) : pm_render_dashboard( $user );
}

function pm_render_header( $user ) {
	$s = pm_settings();
	ob_start();
	?>
	<header class="pm-top">
		<a class="pm-brand" href="<?php echo esc_url( pm_page_url() ); ?>">
			<?php if ( $s['logo_url'] ) : ?>
				<img src="<?php echo esc_url( $s['logo_url'] ); ?>" alt="<?php echo esc_attr( $s['program_name'] ); ?>">
			<?php else : ?>
				<span><?php echo esc_html( $s['program_name'] ); ?></span>
			<?php endif; ?>
		</a>
		<nav>
			<a href="<?php echo esc_url( pm_page_url() ); ?>">Mi panel</a>
			<span class="pm-user"><?php echo esc_html( $user->display_name ); ?></span>
			<a href="<?php echo esc_url( wp_logout_url( pm_page_url() ) ); ?>">Salir</a>
		</nav>
	</header>
	<?php if ( current_user_can( 'manage_options' ) && ! pm_is_sample( $user ) ) : ?>
		<div class="pm-notice">Vista previa de administrador. Así ve la clase un alumno muestra.</div>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

function pm_render_login() {
	$s     = pm_settings();
	$error = isset( $_GET['pm_login_error'] ) ? sanitize_key( $_GET['pm_login_error'] ) : '';
	ob_start();
	?>
	<div class="pm-app">
		<div class="pm-login">
			<?php if ( $s['logo_url'] ) : ?>
				<img class="pm-login-logo" src="<?php echo esc_url( $s['logo_url'] ); ?>" alt="">
			<?php endif; ?>
			<p class="pm-kicker">Clase muestra · <?php echo esc_html( $s['program_name'] ); ?></p>
			<h2><?php echo esc_html( $s['class_title'] ); ?></h2>
			<p class="pm-muted">Ingresa con tu email y la contraseña que te enviamos.</p>
			<?php if ( $error ) : ?>
				<div class="pm-error"><?php echo 'expired' === $error ? 'La sesión expiró, inténtalo de nuevo.' : 'El email o la contraseña no son correctos.'; ?></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( pm_page_url() ); ?>">
				<input type="hidden" name="pm_login" value="1">
				<?php wp_nonce_field( 'pm_login', '_pmnonce' ); ?>
				<label>Email<input type="email" name="email" autocomplete="email" required></label>
				<label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label>
				<button class="pm-btn pm-btn-block" type="submit">Ingresar</button>
			</form>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function pm_render_dashboard( $user ) {
	$s       = pm_settings();
	$p       = pm_get_progress( $user->ID );
	$overall = pm_overall_pct( $p );
	$others  = array_filter( array_map( 'trim', preg_split( '/\R/', $s['other_classes'] ) ) );
	$name    = $user->first_name ? $user->first_name : $user->display_name;
	ob_start();
	?>
	<div class="pm-app">
		<?php echo pm_render_header( $user ); // phpcs:ignore ?>
		<section class="pm-hero">
			<p class="pm-kicker">Mi panel de alumno</p>
			<h1>¡Hola, <?php echo esc_html( $name ); ?>!</h1>
			<p>Bienvenido(a) a <?php echo esc_html( $s['program_name'] ); ?>. Tienes acceso completo a la clase <strong><?php echo esc_html( $s['class_title'] ); ?></strong>.</p>
		</section>

		<?php if ( $p['completed_at'] ) : ?>
			<div class="pm-card pm-done-banner">
				<div>
					<h3>🎓 ¡Completaste <?php echo esc_html( $s['program_name'] ); ?>!</h3>
					<p>Terminaste el <?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) $p['completed_at'] ) ); ?>. Tu certificado está listo.</p>
				</div>
				<a class="pm-btn" href="<?php echo esc_url( pm_cert_url() ); ?>" target="_blank">Ver certificado</a>
			</div>
		<?php endif; ?>

		<div class="pm-card pm-progress-card">
			<div class="pm-progress-head">
				<strong>Tu avance</strong><span><?php echo (int) $overall; ?>%</span>
			</div>
			<div class="pm-bar"><span style="width:<?php echo (int) $overall; ?>%"></span></div>
			<ul class="pm-steps">
				<li class="<?php echo $p['video_pct'] >= 90 ? 'is-done' : ''; ?>">Video (<?php echo (int) $p['video_pct']; ?>%)</li>
				<li class="<?php echo ( $p['pdf_viewed'] || $p['pdf_downloaded'] ) ? 'is-done' : ''; ?>">Material PDF</li>
				<li class="<?php echo $p['quiz_passed'] ? 'is-done' : ''; ?>">Quiz</li>
				<li class="<?php echo $p['completed_at'] ? 'is-done' : ''; ?>">Certificado</li>
			</ul>
		</div>

		<h2 class="pm-section-title">Currículo</h2>
		<div class="pm-grid">
			<a class="pm-class is-open" href="<?php echo esc_url( pm_page_url( array( 'pm_vista' => 'clase' ) ) ); ?>">
				<span class="pm-class-tag"><?php echo $p['completed_at'] ? 'Completada ✓' : 'Disponible'; ?></span>
				<h3><?php echo esc_html( $s['class_title'] ); ?></h3>
				<p><?php echo esc_html( $s['class_desc'] ); ?></p>
				<span class="pm-btn pm-btn-small"><?php echo $p['started_at'] ? ( $p['completed_at'] ? 'Repasar' : 'Continuar' ) : 'Comenzar'; ?></span>
			</a>
			<?php foreach ( $others as $title ) : ?>
				<div class="pm-class is-locked" aria-disabled="true">
					<span class="pm-class-tag">🔒 Programa completo</span>
					<h3><?php echo esc_html( $title ); ?></h3>
					<p>Disponible al inscribirte en <?php echo esc_html( $s['program_name'] ); ?>.</p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function pm_render_class( $user ) {
	$s         = pm_settings();
	$p         = pm_get_progress( $user->ID );
	$video     = pm_video_info( $s['video_url'] );
	$questions = pm_parse_quiz( $s['quiz'] );

	if ( 'vimeo' === $video['type'] ) {
		wp_enqueue_script( 'pm-vimeo' );
	}
	wp_enqueue_script( 'pm-front' );
	wp_localize_script(
		'pm-front',
		'PM',
		array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'pm_ajax' ),
			'video'     => $video,
			'segments'  => (string) $p['video_segments'],
			'passScore' => (int) $s['pass_score'],
			'certUrl'   => pm_cert_url(),
			'panelUrl'  => pm_page_url(),
		)
	);

	ob_start();
	?>
	<div class="pm-app">
		<?php echo pm_render_header( $user ); // phpcs:ignore ?>
		<a class="pm-back" href="<?php echo esc_url( pm_page_url() ); ?>">← Volver a mi panel</a>
		<p class="pm-kicker"><?php echo esc_html( $s['program_name'] ); ?> · Clase muestra</p>
		<h1 class="pm-class-title"><?php echo esc_html( $s['class_title'] ); ?></h1>
		<p class="pm-muted"><?php echo esc_html( $s['class_desc'] ); ?></p>

		<section class="pm-card">
			<h2>1. Video de la clase</h2>
			<div class="pm-video" id="pm-video">
				<?php if ( 'youtube' === $video['type'] ) : ?>
					<div id="pm-yt"></div>
				<?php elseif ( 'vimeo' === $video['type'] ) : ?>
					<iframe id="pm-vimeo" src="<?php echo esc_url( $video['url'] ); ?>" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
				<?php elseif ( 'file' === $video['type'] ) : ?>
					<video id="pm-html5" src="<?php echo esc_url( $video['url'] ); ?>" controls playsinline preload="metadata"></video>
				<?php else : ?>
					<div class="pm-empty">El video aún no se ha configurado.</div>
				<?php endif; ?>
			</div>
			<div class="pm-video-meta">
				<div class="pm-bar"><span id="pm-video-bar" style="width:<?php echo (int) $p['video_pct']; ?>%"></span></div>
				<small><span id="pm-video-pct"><?php echo (int) $p['video_pct']; ?></span>% visto · puedes adelantar y retroceder libremente</small>
			</div>
		</section>

		<section class="pm-card">
			<h2>2. Material de la clase (PDF)</h2>
			<?php if ( $s['pdf_url'] ) : ?>
				<div class="pm-pdf-actions">
					<button type="button" class="pm-btn pm-btn-outline" id="pm-pdf-toggle">Ver PDF aquí</button>
					<a class="pm-btn pm-btn-outline" href="<?php echo esc_url( $s['pdf_url'] ); ?>" target="_blank" rel="noopener" data-pm-track="pdf_viewed">Abrir en otra pestaña</a>
					<a class="pm-btn" href="<?php echo esc_url( $s['pdf_url'] ); ?>" download data-pm-track="pdf_downloaded">Descargar PDF</a>
				</div>
				<div class="pm-pdf" id="pm-pdf" hidden>
					<iframe data-src="<?php echo esc_url( $s['pdf_url'] ); ?>#view=FitH" title="PDF de la clase"></iframe>
				</div>
			<?php else : ?>
				<div class="pm-empty">El PDF aún no se ha configurado.</div>
			<?php endif; ?>
		</section>

		<section class="pm-card" id="pm-quiz-card">
			<h2>3. Quiz</h2>
			<?php if ( $p['quiz_passed'] ) : ?>
				<p class="pm-ok">Ya aprobaste el quiz con <?php echo (int) $p['quiz_score']; ?>%. Puedes hacerlo de nuevo si quieres repasar.</p>
			<?php endif; ?>
			<?php if ( ! $questions ) : ?>
				<div class="pm-empty">El quiz aún no se ha configurado.</div>
			<?php else : ?>
				<form id="pm-quiz">
					<?php foreach ( $questions as $qi => $q ) : ?>
						<fieldset class="pm-q" data-q="<?php echo (int) $qi; ?>">
							<legend><span><?php echo (int) $qi + 1; ?>.</span> <?php echo esc_html( $q['question'] ); ?></legend>
							<?php
							$multi = count( $q['correct'] ) > 1;
							foreach ( $q['options'] as $oi => $opt ) :
								?>
								<label class="pm-opt">
									<input type="<?php echo $multi ? 'checkbox' : 'radio'; ?>" name="q<?php echo (int) $qi; ?><?php echo $multi ? '[]' : ''; ?>" value="<?php echo (int) $oi; ?>">
									<span><?php echo esc_html( $opt ); ?></span>
								</label>
							<?php endforeach; ?>
							<?php if ( $multi ) : ?><small class="pm-muted">Selecciona todas las correctas.</small><?php endif; ?>
						</fieldset>
					<?php endforeach; ?>
					<div class="pm-quiz-foot">
						<small class="pm-muted">Necesitas <?php echo (int) $s['pass_score']; ?>% para aprobar.</small>
						<button type="submit" class="pm-btn">Enviar respuestas</button>
					</div>
					<div id="pm-quiz-result" aria-live="polite"></div>
				</form>
			<?php endif; ?>
		</section>

		<div class="pm-celebrate" id="pm-celebrate" hidden>
			<canvas id="pm-confetti"></canvas>
			<div class="pm-celebrate-box">
				<div class="pm-medal">🎓</div>
				<p class="pm-kicker">¡Lo lograste!</p>
				<h2>¡Felicidades, <?php echo esc_html( $user->first_name ? $user->first_name : $user->display_name ); ?>!</h2>
				<p>Has completado el currículo de <strong><?php echo esc_html( $s['program_name'] ); ?></strong>. Tu compromiso y tu deseo de crecer como líder inspiran a quienes te rodean.</p>
				<div class="pm-celebrate-actions">
					<a class="pm-btn" href="<?php echo esc_url( pm_cert_url() ); ?>" target="_blank">Ver mi certificado</a>
					<a class="pm-btn pm-btn-outline" href="<?php echo esc_url( pm_page_url() ); ?>">Ir a mi panel</a>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * AJAX: seguimiento y quiz
 * ---------------------------------------------------------------------- */

function pm_ajax_guard() {
	check_ajax_referer( 'pm_ajax', 'nonce' );
	if ( ! is_user_logged_in() || ! pm_can_view() ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	return get_current_user_id();
}

add_action( 'wp_ajax_pm_track', 'pm_ajax_track' );
function pm_ajax_track() {
	$uid  = pm_ajax_guard();
	$what = sanitize_key( $_POST['what'] ?? '' );
	$p    = pm_get_progress( $uid );

	if ( 'video' === $what ) {
		// Segmentos vistos (100 caracteres "0"/"1") para medir lo realmente visto, aunque adelanten.
		$incoming = preg_replace( '/[^01]/', '', (string) ( $_POST['segments'] ?? '' ) );
		$incoming = str_pad( substr( $incoming, 0, 100 ), 100, '0' );
		$current  = str_pad( (string) $p['video_segments'], 100, '0' );
		$merged   = '';
		for ( $i = 0; $i < 100; $i++ ) {
			$merged .= ( '1' === $incoming[ $i ] || '1' === $current[ $i ] ) ? '1' : '0';
		}
		$pct = substr_count( $merged, '1' );
		$p   = pm_update_progress( $uid, array( 'video_segments' => $merged, 'video_pct' => $pct ) );
		wp_send_json_success( array( 'video_pct' => $pct ) );
	}

	if ( in_array( $what, array( 'pdf_viewed', 'pdf_downloaded' ), true ) ) {
		$changes = array( $what => 1 );
		if ( 'pdf_downloaded' === $what ) {
			$changes['pdf_viewed'] = 1;
		}
		pm_update_progress( $uid, $changes );
		wp_send_json_success();
	}

	if ( 'start' === $what ) {
		pm_update_progress( $uid, array() );
		wp_send_json_success();
	}

	wp_send_json_error( 'unknown' );
}

add_action( 'wp_ajax_pm_quiz', 'pm_ajax_quiz' );
function pm_ajax_quiz() {
	$uid       = pm_ajax_guard();
	$questions = pm_parse_quiz();
	$answers   = isset( $_POST['answers'] ) ? json_decode( wp_unslash( $_POST['answers'] ), true ) : array();
	$answers   = is_array( $answers ) ? $answers : array();

	if ( ! $questions ) {
		wp_send_json_error( 'no_quiz' );
	}

	$correct = 0;
	$detail  = array();
	foreach ( $questions as $qi => $q ) {
		$given = isset( $answers[ $qi ] ) ? array_map( 'intval', (array) $answers[ $qi ] ) : array();
		sort( $given );
		$expected = $q['correct'];
		sort( $expected );
		$ok = $given === $expected;
		if ( $ok ) {
			$correct++;
		}
		$detail[ $qi ] = $ok;
	}

	$score  = (int) round( $correct / count( $questions ) * 100 );
	$passed = $score >= (int) pm_setting( 'pass_score' );
	$p      = pm_get_progress( $uid );

	$changes = array(
		'quiz_score'    => max( $score, (int) $p['quiz_score'] ),
		'quiz_attempts' => (int) $p['quiz_attempts'] + 1,
	);
	$first_completion = false;
	if ( $passed ) {
		$changes['quiz_passed'] = 1;
		if ( ! $p['completed_at'] ) {
			$changes['completed_at'] = time();
			$changes['cert_code']    = 'SAMPLE-' . strtoupper( wp_generate_password( 8, false ) );
			$first_completion        = true;
		}
	}
	pm_update_progress( $uid, $changes );

	wp_send_json_success(
		array(
			'score'     => $score,
			'correct'   => $correct,
			'total'     => count( $questions ),
			'passed'    => $passed,
			'detail'    => $detail,
			'firstTime' => $first_completion,
		)
	);
}

/* -------------------------------------------------------------------------
 * Certificado con marca de agua SAMPLE
 * ---------------------------------------------------------------------- */

function pm_cert_url() {
	return add_query_arg( 'pm_certificado', '1', home_url( '/' ) );
}

add_action( 'template_redirect', 'pm_render_certificate' );
function pm_render_certificate() {
	if ( empty( $_GET['pm_certificado'] ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( pm_page_url() );
		exit;
	}
	$user = wp_get_current_user();
	$p    = pm_get_progress( $user->ID );
	if ( ! pm_can_view( $user ) || ( ! $p['completed_at'] && ! current_user_can( 'manage_options' ) ) ) {
		wp_safe_redirect( pm_page_url() );
		exit;
	}
	$s    = pm_settings();
	$date = date_i18n( 'j \d\e F \d\e Y', $p['completed_at'] ? (int) $p['completed_at'] : time() );
	$code = $p['cert_code'] ? $p['cert_code'] : 'SAMPLE-PREVIEW';
	nocache_headers();
	include PM_DIR . 'templates/certificate.php';
	exit;
}
