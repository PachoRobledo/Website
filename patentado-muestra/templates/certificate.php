<?php
/**
 * Plantilla del certificado (con marca de agua SAMPLE).
 *
 * Variables: $user, $s (ajustes), $date, $code.
 */
defined( 'ABSPATH' ) || exit;
$pm_name = trim( $user->first_name . ' ' . $user->last_name );
$pm_name = $pm_name ? $pm_name : $user->display_name;
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Certificado de muestra · <?php echo esc_html( $s['program_name'] ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
	:root { --p: <?php echo esc_attr( $s['primary_color'] ); ?>; --a: <?php echo esc_attr( $s['accent_color'] ); ?>; }
	* { box-sizing: border-box; }
	body { margin: 0; background: #eef0f3; font-family: Inter, system-ui, sans-serif; color: #1d2433; }
	.bar { display: flex; gap: 10px; justify-content: center; padding: 16px; flex-wrap: wrap; }
	.bar button, .bar a { font: 600 14px Inter, sans-serif; border: 0; border-radius: 8px; padding: 10px 18px; cursor: pointer; text-decoration: none; background: var(--p); color: #fff; }
	.bar a { background: #fff; color: var(--p); border: 1px solid var(--p); }
	.wrap { padding: 0 16px 32px; display: flex; justify-content: center; }
	.cert { position: relative; width: 1100px; max-width: 100%; aspect-ratio: 1.414 / 1; background: #fffdf8; overflow: hidden;
		box-shadow: 0 10px 40px rgba(0,0,0,.15); padding: 4%; }
	.frame { position: absolute; inset: 2.2%; border: 3px solid var(--p); }
	.frame::after { content: ""; position: absolute; inset: 8px; border: 1px solid var(--a); }
	.content { position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 0 6%; }
	.logo { max-height: 70px; max-width: 220px; margin-bottom: 2%; }
	.program { font: 600 clamp(11px, 1.4vw, 15px) Inter, sans-serif; letter-spacing: .3em; text-transform: uppercase; color: var(--a); }
	h1 { font: 700 clamp(26px, 5vw, 58px) "Cormorant Garamond", serif; color: var(--p); margin: 1% 0 2%; }
	.lead { font-size: clamp(11px, 1.4vw, 16px); color: #5a6273; margin: 0; }
	.name { font: 700 clamp(28px, 5.4vw, 64px) "Cormorant Garamond", serif; margin: 2% 0 1%; padding: 0 4% 1%; border-bottom: 2px solid var(--a); }
	.what { font-size: clamp(12px, 1.5vw, 18px); max-width: 720px; line-height: 1.5; margin: 2% 0 4%; }
	.what strong { color: var(--p); }
	.foot { display: flex; justify-content: space-between; width: 100%; max-width: 760px; gap: 24px; font-size: clamp(10px, 1.2vw, 13px); color: #5a6273; }
	.foot div { flex: 1; border-top: 1px solid #c8ccd4; padding-top: 6px; }
	.foot strong { display: block; color: #1d2433; font-size: 1.1em; }
	.watermark { position: absolute; inset: 0; z-index: 3; pointer-events: none; display: flex; align-items: center; justify-content: center; }
	.watermark span { font: 800 clamp(90px, 22vw, 260px) Inter, sans-serif; color: rgba(200, 30, 30, .16); transform: rotate(-28deg); letter-spacing: .08em;
		-webkit-text-stroke: 2px rgba(200, 30, 30, .25); }
	.tile { position: absolute; inset: -20%; z-index: 1; pointer-events: none; transform: rotate(-28deg); display: flex; flex-wrap: wrap; gap: 30px 60px; align-content: flex-start;
		font: 700 18px Inter, sans-serif; color: rgba(200, 30, 30, .06); }
	.tag { position: absolute; z-index: 4; top: 4.5%; right: 4.5%; background: #c81e1e; color: #fff; font: 700 11px Inter, sans-serif; letter-spacing: .15em; padding: 6px 10px; border-radius: 4px; }
	@media print {
		@page { size: A4 landscape; margin: 0; }
		body { background: #fff; }
		.bar { display: none; }
		.wrap { padding: 0; }
		.cert { width: 100vw; height: 100vh; aspect-ratio: auto; box-shadow: none; }
		* { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
	}
</style>
</head>
<body>
	<div class="bar">
		<button type="button" id="dl">Descargar PDF</button>
		<button type="button" onclick="window.print()">Imprimir</button>
		<a href="<?php echo esc_url( pm_page_url() ); ?>">Volver a mi panel</a>
	</div>
	<div class="wrap">
		<div class="cert" id="cert">
			<div class="frame"></div>
			<div class="tile" aria-hidden="true"><?php echo str_repeat( '<span>SAMPLE</span>', 160 ); // phpcs:ignore ?></div>
			<div class="tag">SAMPLE · MUESTRA</div>
			<div class="content">
				<?php if ( $s['logo_url'] ) : ?>
					<img class="logo" src="<?php echo esc_url( $s['logo_url'] ); ?>" alt="" crossorigin="anonymous">
				<?php endif; ?>
				<div class="program"><?php echo esc_html( $s['program_name'] ); ?></div>
				<h1>Certificado de Finalización</h1>
				<p class="lead">Se otorga con reconocimiento a</p>
				<div class="name"><?php echo esc_html( $pm_name ); ?></div>
				<p class="what">por haber completado satisfactoriamente la clase <strong><?php echo esc_html( $s['class_title'] ); ?></strong> del currículo <strong><?php echo esc_html( $s['program_name'] ); ?></strong>.</p>
				<div class="foot">
					<div><strong><?php echo esc_html( $date ); ?></strong>Fecha</div>
					<div><strong><?php echo esc_html( $s['signer_name'] ? $s['signer_name'] : $s['program_name'] ); ?></strong><?php echo esc_html( $s['signer_title'] ); ?></div>
					<div><strong><?php echo esc_html( $code ); ?></strong>Código</div>
				</div>
			</div>
			<div class="watermark" aria-hidden="true"><span>SAMPLE</span></div>
		</div>
	</div>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
	<script>
	document.getElementById('dl').addEventListener('click', function () {
		var btn = this;
		if (!window.html2canvas || !window.jspdf) { window.print(); return; }
		btn.disabled = true; btn.textContent = 'Generando…';
		html2canvas(document.getElementById('cert'), { scale: 2, useCORS: true, backgroundColor: '#fffdf8' }).then(function (canvas) {
			var pdf = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
			var w = pdf.internal.pageSize.getWidth(), h = pdf.internal.pageSize.getHeight();
			pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, w, h);
			pdf.save('certificado-muestra.pdf');
		}).catch(function () { window.print(); }).finally(function () {
			btn.disabled = false; btn.textContent = 'Descargar PDF';
		});
	});
	</script>
</body>
</html>
