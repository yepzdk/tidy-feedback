/**
 * @file
 * JavaScript for the feedback form.
 */
(function (Drupal, once) {
  "use strict";
  
  // Use Drupal's API to get jQuery
  const $ = Drupal.jQuery;

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
    },
  };
})(Drupal, once);
