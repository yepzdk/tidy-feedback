/**
 * @file
 * JavaScript for the feedback modal.
 */
(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.tidyFeedbackModal = {
    attach: function (context, settings) {
      // We'll keep this simple to avoid once() issues
      // The modal functionality is now handled directly in the highlighter JS
    },
  };
})(jQuery, Drupal);
