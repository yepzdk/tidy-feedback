/**
 * @file
 * JavaScript for the feedback modal.
 */
(function (Drupal) {
  "use strict";
  
  // Get jQuery in a way that works in both Drupal 9+ and Drupal 11
  const $ = jQuery;

  Drupal.behaviors.tidyFeedbackModal = {
    attach: function (context, settings) {
      // We'll keep this simple to avoid once() issues
      // The modal functionality is now handled directly in the highlighter JS
    },
  };
})(Drupal);
