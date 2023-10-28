jQuery(document).ready(function($) {
	// Only show language dropdown for JFT
	if ($('#fetch_meditation_book').val() === 'spad') {
		$('#language-container').hide();
	}
	$('#fetch_meditation_book').change(function() {
		if ($(this).val() === 'jft') {
			$('#language-container').show();
		} else {
			$('#language-container').hide();
		}
	});
});
