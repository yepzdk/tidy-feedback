/**
 * @file
 * JavaScript for the tidy feedback form page.
 */
(function (Drupal, once) {
  "use strict";

  const $ = jQuery;

  Drupal.behaviors.tidyFeedbackFormPage = {
    attach: function (context, settings) {
      // Set browser information on page load
      once('tidy-feedback-form-page', '#tidy-feedback-browser-info', context).forEach(function(element) {
        setBrowserInfo(element);
      });

      /**
       * Sets browser information in the hidden field.
       * 
       * @param {HTMLElement} element
       *   The browser info input element.
       */
      function setBrowserInfo(element) {
        try {
          // Collect browser information
          var browserInfo = {
            userAgent: navigator.userAgent,
            screenWidth: window.screen.width,
            screenHeight: window.screen.height,
            viewportWidth: window.innerWidth,
            viewportHeight: window.innerHeight,
            devicePixelRatio: window.devicePixelRatio || 1,
            platform: navigator.platform,
            language: navigator.language,
            timestamp: new Date().toISOString()
          };

          // Convert to JSON and set in the hidden field
          element.value = JSON.stringify(browserInfo);
        } catch (error) {
          console.error('Error setting browser info:', error);
        }
      }
    }
  };

})(Drupal, once);