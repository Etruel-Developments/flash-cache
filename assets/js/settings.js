function hooks_cookie() {
	jQuery(".delete").click(function (e) {
		jQuery(this).parent().parent().remove();
	});
}
jQuery(document).ready(function ($) {
	$('.reset-data-button').insertAfter('#search-submit');

	hooks_cookie();
	$("#add_new_dont_cache_cookie").click(function (e) {
		$("#table_dont_cache_cookie").append(
				'<tr valign="top" class="tr_item_dont_cache_cookie"><td scope="row"><input type="text" name="flash_cache_advanced[dont_cache_cookie][]" value=""/><label title="" data-id="1" class="delete"><span class="dashicons dashicons-trash"></span></label></td></tr>'
				);
		hooks_cookie();
	});

	$(".wpm_menu_close").click(function (e) {
		$(".wpm_container").toggleClass("show_menu");
		return false;
	});
	$(".btn_reset_to_default").click(function (e) {
		if (!confirm("Are you sure you want to reset to defaults?")) {
			e.preventDefault();
		}
	});

	$(init);
	function init() {
		$('.flash-wrap-notices').append($('.error, .success, .notice, .message, .fade, .updated'));
	}

	if ($('input[name="flash_cache_advanced[optimize_styles]"]:checked').val() == '1') {
		$('#advanced_settings_styles').show();
	} else {
		$('#advanced_settings_styles').hide();
	}

	$('input[name="flash_cache_advanced[optimize_styles]"]').change(function () {
		if ($(this).val() == '1') {
			$('#advanced_settings_styles').show();
		} else {
			$('#advanced_settings_styles').hide();
			$('input[name="flash_cache_advanced[minify_styles]"]').prop('checked', false);
			$('input[name="flash_cache_advanced[optimize_google_fonts]"]').prop('checked', false);
		}
	});

	if ($('input[name="flash_cache_advanced[optimize_scripts]"]:checked').val() == '1') {
		$('.flash_cache_avoid_optimize').show();
		$('.flash_cache_allow_optimize').show();
		$('.advanced-minify-scripts').show();
	} else {
		$('.flash_cache_allow_optimize').hide();
		$('.advanced-minify-scripts').hide();
		$('input[name="flash_cache_advanced[minify_scripts]"]').prop('checked', false);
	}

	$('input[name="flash_cache_advanced[optimize_scripts]"]').change(function () {
		if ($(this).val() == '1') {
			$('.flash_cache_avoid_optimize').show();
			$('.flash_cache_allow_optimize').show();
			$('.advanced-minify-scripts').show();
		} else {
			$('.flash_cache_avoid_optimize').hide();
			$('.flash_cache_allow_optimize').hide();
			$('.advanced-minify-scripts').hide();
			$('input[name="flash_cache_advanced[minify_scripts]"]').prop('checked', false);
		}
	});


	// Check the initial value of the radio button
	if ($('input[name="flash_cache_advanced[plugins_files]"]:checked').val() == '1') {
		$('#plugins_to_exclude').show();  // Show the checkboxes if "On" is selected by default
	}

	// Handle change event on radio buttons
	$('input[name="flash_cache_advanced[plugins_files]"]').change(function () {
		if ($(this).val() == '1') {
			$('#plugins_to_exclude').show();  // Show the checkboxes if "On" is selected
		} else {
			$('#plugins_to_exclude').hide();  // Hide the checkboxes if "Off" is selected
		}
	});

	// Check the initial value of the radio button
	if ($('input[name="flash_cache_advanced[avoid_optimize]"]:checked').val() == '1') {
		$('#textarea_avoid_optimize').show();  // Show the textarea if "On" is selected by default
	}
	// Handle change event on radio buttons
	$('input[name="flash_cache_advanced[avoid_optimize]"]').change(function () {
		if ($(this).val() == '1') {
			$('#textarea_avoid_optimize').show();  // Show the textarea if "On" is selected
		} else {
			$('#textarea_avoid_optimize').hide();  // Hide the textarea if "Off" is selected
		}
	});

	var lockTypeRadio = $('input[name="flash_cache_advanced[lock_type]"]');
	var previousValue = lockTypeRadio.filter(':checked').val(); // Store the initially checked value

	if (lockTypeRadio.length) {
		lockTypeRadio.on('change', function(event) {
			var confirmation = confirm('Changing this option will clear all the cache. Do you want to proceed?');
			if (!confirmation) {
				event.preventDefault();
				// Reset to previous value
				$(this).prop('checked', false);
				lockTypeRadio.filter('[value="' + previousValue + '"]').prop('checked', true);
			}
		});
	}
});