<div id="warranty_settings_default">

	<?php WC_Admin_Settings::output_fields( $settings['default'] ); ?>

</div>
<script>
	jQuery("document").ready(function($) {
		jQuery("#warranty_default_type").change(function() {
			jQuery(".show-if-addon_warranty").parents("tr").hide();
			jQuery(".show-if-included_warranty").parents("tr").hide();

			switch (jQuery(this).val()) {

				case "included_warranty":
					jQuery(".show-if-included_warranty").parents("tr").show();
					break;

				case "addon_warranty":
					jQuery(".show-if-addon_warranty").parents("tr").show();
					break;

			}
		}).change();

		jQuery("#warranty_default_length").change(function() {
			if ( jQuery(this).val() == "limited" ) {
				jQuery("#warranty_default_length_value").parents("tr").show();
				jQuery("#warranty_default_length_duration").parents("tr").show();
			} else {
				jQuery("#warranty_default_length_value").parents("tr").hide();
				jQuery("#warranty_default_length_duration").parents("tr").hide();
			}
		}).change();
	});
</script>
