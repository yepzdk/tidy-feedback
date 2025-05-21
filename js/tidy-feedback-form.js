/**
 * @file
 * JavaScript for the feedback form.
 */
(function (Drupal, once) {
  "use strict";
  
  // Get jQuery in a way that works in both Drupal 9+ and Drupal 11
  const $ = jQuery;

  Drupal.behaviors.tidyFeedbackForm = {
    attach: function (context, settings) {
      // Form-specific behaviors
      once("tidy-feedback-form", "#tidy-feedback-form", context).forEach(
        function (form) {
          // Pre-fill browser info when form loads
          $("#tidy-feedback-browser-info").val(getBrowserInfo());

          // Pre-fill URL if not already set
          if (!$("#tidy-feedback-url").val()) {
            $("#tidy-feedback-url").val(window.location.href);
          }
          
          // Add file upload validation
          $("#tidy-feedback-file").on("change", function(e) {
            validateFileUpload(this);
          });
        },
      );

      // Helper function to get browser information
      function getBrowserInfo() {
        const ua = navigator.userAgent;
        const browserInfo = {
          userAgent: ua,
          screenWidth: window.screen.width,
          screenHeight: window.screen.height,
          viewportWidth: window.innerWidth,
          viewportHeight: window.innerHeight,
          devicePixelRatio: window.devicePixelRatio || 1,
        };

        return JSON.stringify(browserInfo);
      }
      
      // Helper function to validate file uploads
      function validateFileUpload(fileInput) {
        const maxSize = 2 * 1024 * 1024; // 2MB
        const file = fileInput.files[0];
        
        // Clear previous error messages
        $(fileInput).next('.file-upload-error').remove();
        
        if (file) {
          // Check file size
          if (file.size > maxSize) {
            $(fileInput).after('<div class="file-upload-error messages messages--error">' + 
              Drupal.t('The file is too large. Maximum file size is 2MB.') + '</div>');
            fileInput.value = '';
          }
        }
      }
    },
  };
})(Drupal, once);
