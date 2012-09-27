jQuery(document).ready(function() {
	var $notifier = jQuery('#header').find('.notifier');
	var $fieldSelect = jQuery('#field');

	Symphony.RestEngineFields = {
		sectionChange: function() {
			var sectionID = jQuery(this).val();
			jQuery.ajax({
				type: 'get',
				url: Symphony.Context.get('root') + '/symphony/extension/restengine/fields/' + sectionID,
				async: false,
				beforeSend: function() {
					$fieldSelect.prop({disabled: true});
				},
				success: function(data, response) {
					if(response === "success") {
						var options = "";
						jQuery.each(data, function(index, value) {
							options += '<option value="' + value.id + '">' + value.title + '</option>';
						});
						$fieldSelect.prop({disabled: false}).empty().html(options);
					}
					else {
						$notifier.trigger('attach.notify', [
							Symphony.Language.get('An error occurred while processing this form.'),
							'error restengine'
						]);
					}
				},
				error: function(data, response) {
					var errorMessage = jQuery.parseJSON(data.responseText);
					$notifier.trigger('attach.notify', [
						Symphony.Language.get(errorMessage.error),
						'error restengine'
					]);
				}
			});
			return false;
		}
	};

	// Listen for when the section changes
	jQuery('#section').change(Symphony.RestEngineFields.sectionChange);
});
