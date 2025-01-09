/**
 * Admin interface functionality for Edge Images plugin.
 *
 * Handles dynamic UI interactions in the plugin's admin interface.
 */
(function($) {
    'use strict';

    /**
     * Initialize admin functionality.
     */
    function init() {
        const $providerInputs = $('input[name="edge_images_provider"]');
        
        // Initial state
        toggleProviderFields();
        handleDisabledFeatures();
        
        // On change
        $providerInputs.on('change', toggleProviderFields);
    }

    /**
     * Toggle visibility of provider-specific fields based on selection.
     */
    function toggleProviderFields() {
        const selectedProvider = $('input[name="edge_images_provider"]:checked').val();
        
        // Hide all provider-specific fields first
        $('.edge-images-provider-field').hide();
        
        // Show fields for selected provider
        if (selectedProvider) {
            $(`.edge-images-${selectedProvider}-field`).show();
        }
    }

    /**
     * Handle disabled features in the admin interface.
     */
    function handleDisabledFeatures() {
        $('.edge-images-feature-disabled').each(function() {
            const $feature = $(this);
            const $checkbox = $feature.find('input[type="checkbox"]');
            
            // Disable the checkbox
            $checkbox.prop('disabled', true);
            
            // If it was checked, uncheck it
            if ($checkbox.prop('checked')) {
                $checkbox.prop('checked', false);
            }
        });
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery); 