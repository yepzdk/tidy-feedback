/**
 * @file
 * JavaScript for the templated feedback form.
 */
(function (Drupal) {
  "use strict";

  // Get jQuery in a way that works in both Drupal 9+ and Drupal 11
  const $ = jQuery;

  Drupal.behaviors.tidyFeedbackTemplateForm = {
    attach: function (context, settings) {
      // Only run once for the form
      $(once('tidy-feedback-template-form', '#tidy-feedback-form', context)).each(function() {
        const form = this;
        
        // Pre-fill browser info when form loads
        setBrowserInfo();
        
        // Set up form submission handling
        $(form).on('submit', function(e) {
          e.preventDefault();
          
          // Show loading state
          $('#tidy-feedback-submit').prop('disabled', true).text(Drupal.t('Submitting...'));
          
          // Create FormData object for file upload
          const formData = new FormData(form);
          
          // Get CSRF token 
          getCsrfToken(function(token) {
            // Add CSRF token to headers
            $.ajax({
              url: Drupal.url("tidy-feedback/submit"),
              type: "POST",
              data: formData,
              processData: false,
              contentType: false,
              headers: {
                'X-CSRF-Token': token
              },
              success: function (response) {
                // Reset form
                form.reset();
                
                // Close dialog if in dialog mode
                const dialogObj = $(form).closest('.ui-dialog-content').data("drupalDialog");
                if (dialogObj && typeof dialogObj.close === "function") {
                  dialogObj.close();
                  // Show success message after dialog closes
                  showSuccessMessage();
                } else {
                  // Show success message in place
                  $('#tidy-feedback-result').html(
                    '<div class="messages messages--status">' +
                    Drupal.t("Thank you for your feedback. It has been submitted successfully.") +
                    '</div>'
                  );
                }
                
                // Reset submit button
                $('#tidy-feedback-submit').prop('disabled', false).text(Drupal.t('Submit Feedback'));
              },
              error: function (xhr, status, error) {
                console.error("Form submission error:", xhr.responseText);
                
                // Show error message
                $('#tidy-feedback-result').html(
                  '<div class="messages messages--error">' +
                  Drupal.t("Error submitting feedback. Please try again.") +
                  '</div>'
                );
                
                // Reset submit button
                $('#tidy-feedback-submit').prop('disabled', false).text(Drupal.t('Submit Feedback'));
              }
            });
          });
        });
        
        // Handle cancel button click
        $('#tidy-feedback-cancel').on('click', function() {
          // If in dialog, close it
          const dialogObj = $(form).closest('.ui-dialog-content').data("drupalDialog");
          if (dialogObj && typeof dialogObj.close === "function") {
            dialogObj.close();
          } else {
            // Otherwise just reset the form
            form.reset();
            $('#tidy-feedback-result').empty();
          }
        });
      });
    }
  };

  /**
   * Sets browser information in the hidden input field.
   */
  function setBrowserInfo() {
    var browserInfo = {
      userAgent: navigator.userAgent,
      screenWidth: window.screen.width,
      screenHeight: window.screen.height,
      viewportWidth: window.innerWidth,
      viewportHeight: window.innerHeight,
      devicePixelRatio: window.devicePixelRatio || 1,
      platform: navigator.platform,
      language: navigator.language,
    };

    // Set the value as a properly formatted JSON string
    $("#tidy-feedback-browser-info").val(JSON.stringify(browserInfo));
  }

  /**
   * Get CSRF token for form submission.
   *
   * @param {function} callback - The callback to run with the token.
   */
  function getCsrfToken(callback) {
    if (drupalSettings.token) {
      callback(drupalSettings.token);
    }
    else {
      $.ajax({
        url: Drupal.url('session/token'),
        type: 'GET',
        dataType: 'text',
        success: function(token) {
          callback(token);
        },
        error: function(xhr, status, error) {
          console.error('Error getting CSRF token:', error);
        }
      });
    }
  }

  /**
   * Shows a success message after feedback submission.
   */
  function showSuccessMessage() {
    const message = $('<div class="tidy-feedback-success-message"></div>')
      .text(Drupal.t("Feedback submitted successfully"))
      .appendTo("body");

    setTimeout(function () {
      message.fadeOut(400, function () {
        $(this).remove();
      });
    }, 3000);
  }

})(Drupal);