jQuery(function ($) {
	$('.pm-media').on('click', function (e) {
		e.preventDefault();
		var target = $('#' + $(this).data('target'));
		var frame = wp.media({ title: 'Elegir archivo', multiple: false });
		frame.on('select', function () {
			target.val(frame.state().get('selection').first().toJSON().url);
		});
		frame.open();
	});
});
