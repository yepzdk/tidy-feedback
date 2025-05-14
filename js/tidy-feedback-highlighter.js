/**
 * @file
 * JavaScript for highlighting page elements for feedback.
 */
(function (Drupal, drupalSettings, once) {
  "use strict";

  // Get jQuery in a way that works in both Drupal 9+ and Drupal 11
  const $ = jQuery;

  // Variable to track feedback mode state
  let feedbackModeActive = false;

  Drupal.behaviors.tidyFeedbackHighlighter = {
    attach: function (context, settings) {
      // Initialize variables
      const bannerPosition =
        drupalSettings.tidyFeedback?.bannerPosition || "right";
      const highlightColor =
        drupalSettings.tidyFeedback?.highlightColor || "var(--highlightBorderColor)";

      // Only run this once for the document
      if (context === document) {
        // Create the banner if it doesn't exist
        if (!$(".tidy-feedback-banner").length) {
          const banner = $(
            '<div class="tidy-feedback-banner" role="button" tabindex="0"></div>',
          )
            .attr("title", Drupal.t("Click to activate feedback mode"))
            .addClass(`position-${bannerPosition}`);

          $("body").append(banner);
        }

        // Create highlight guides if they don't exist
        if (
          !$(".tidy-feedback-guide-horizontal-top, .tidy-feedback-guide-horizontal-bottom, .tidy-feedback-guide-vertical-start, .tidy-feedback-guide-vertical-end")
            .length
        ) {
          $("body").append(
            $(
              '<div class="tidy-feedback-guide-horizontal-top tidy-feedback-ui"></div>',
            ),
            $(
              '<div class="tidy-feedback-guide-horizontal-bottom tidy-feedback-ui"></div>',
            ),
            $(
              '<div class="tidy-feedback-guide-vertical-start tidy-feedback-ui"></div>',
            ),
            $(
              '<div class="tidy-feedback-guide-vertical-end tidy-feedback-ui"></div>',
            ),
          );

          // Apply highlight color from settings
          $(
            ".tidy-feedback-guide-horizontal-top, .tidy-feedback-guide-horizontal-bottom, .tidy-feedback-guide-vertical-start, .tidy-feedback-guide-vertical-end",
          ).css("border-color", highlightColor);
        }
      }

      // Handle banner click - use once for the banner elements
      once("tidy-feedback-banner", ".tidy-feedback-banner", context).forEach(
        function (banner) {
          $(banner).on("click", function (e) {
            toggleFeedbackMode();
            e.preventDefault();
            e.stopPropagation();
          });
        },
      );
    },
  };

  // Toggle feedback mode
  function toggleFeedbackMode() {
    feedbackModeActive = !feedbackModeActive;

    // Toggle active class on banner
    $(".tidy-feedback-banner").toggleClass("active", feedbackModeActive);

    if (feedbackModeActive) {
      // Create overlay if it doesn't exist
      if (!$("#tidy-feedback-overlay").length) {
        $("body").append(
          '<div id="tidy-feedback-overlay" class="tidy-feedback-ui"></div>',
        );
      }

      // Setup overlay event handlers
      $("#tidy-feedback-overlay")
        .on("mousemove", function (e) {
          handleOverlayMouseMove(e);
        })
        .on("click", function (e) {
          handleOverlayClick(e);
        });

      // Show the overlay
      $("#tidy-feedback-overlay").show();

      // Update banner tooltip
      $(".tidy-feedback-banner").attr(
        "title",
        Drupal.t("Click to deactivate feedback mode"),
      );
    } else {
      // Hide overlay and unbind events
      $("#tidy-feedback-overlay").off("mousemove click").hide();

      // Hide guide lines
      $(
        ".tidy-feedback-guide-horizontal-top, .tidy-feedback-guide-horizontal-bottom, .tidy-feedback-guide-vertical-start, .tidy-feedback-guide-vertical-end",
      ).hide();

      // Update banner tooltip
      $(".tidy-feedback-banner").attr(
        "title",
        Drupal.t("Click to activate feedback mode"),
      );
    }
  }

  // Handle mouse movement over the overlay
  function handleOverlayMouseMove(e) {
    // Get element underneath the overlay
    $("#tidy-feedback-overlay").hide(); // Temporarily hide overlay to find element underneath
    const elementUnder = document.elementFromPoint(e.clientX, e.clientY);
    $("#tidy-feedback-overlay").show(); // Show overlay again

    // Skip if element is part of our UI
    if ($(elementUnder).closest(".tidy-feedback-ui, .ui-dialog").length) {
      $(
        ".tidy-feedback-guide-horizontal-top, .tidy-feedback-guide-horizontal-bottom, .tidy-feedback-guide-vertical-start, .tidy-feedback-guide-vertical-end",
      ).hide();
      return;
    }

    // Get position data
    const $target = $(elementUnder);
    const offset = $target.offset();
    const width = $target.outerWidth();
    const height = $target.outerHeight();

    // Update guide positions
    $(".tidy-feedback-guide-horizontal-top").css({
      top: offset.top,
      display: "block",
    });

    $(".tidy-feedback-guide-horizontal-bottom").css({
      top: offset.top + height,
      display: "block",
    });

    $(".tidy-feedback-guide-vertical-start").css({
      left: offset.left,
      display: "block",
    });

    $(".tidy-feedback-guide-vertical-end").css({
      left: offset.left + width,
      display: "block",
    });
  }

  // Handle click on the overlay
  function handleOverlayClick(e) {
    // Get element underneath the overlay
    $("#tidy-feedback-overlay").hide(); // Temporarily hide overlay
    const elementUnder = document.elementFromPoint(e.clientX, e.clientY);
    $("#tidy-feedback-overlay").show(); // Show overlay again

    // Skip if element is part of our UI
    if ($(elementUnder).closest(".tidy-feedback-ui, .ui-dialog").length) {
      return;
    }

    // Get element selector
    const elementSelector = getElementSelector(elementUnder);

    // Open feedback form
    openFeedbackModal(elementSelector);
  }

  // Get CSS selector for an element
  function getElementSelector(element) {
    let path = [];
    let current = element;

    while (current && current !== document.body) {
      let selector = current.tagName.toLowerCase();

      if (current.id) {
        selector += "#" + current.id;
        path.unshift(selector);
        break;
      } else if (current.className) {
        const classes = current.className.split(/\s+/).filter((c) => c);
        if (classes.length) {
          selector += "." + classes.join(".");
        }
      }

      path.unshift(selector);
      current = current.parentNode;
    }

    return path.join(" > ");
  }

  // Function to open feedback modal
  function openFeedbackModal(elementSelector) {
    // Create a simple form without relying on Drupal's form API
    var simpleForm = `
      <div id="tidy-feedback-form-wrapper">
        <form id="tidy-feedback-simple-form" enctype="multipart/form-data">
          <div class="form-item">
            <label for="issue_type">Issue Type</label>
            <select id="issue_type" name="issue_type" required>
              <option value="bug">Bug</option>
              <option value="enhancement">Enhancement</option>
              <option value="question">Question</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-item">
            <label for="severity">Severity</label>
            <select id="severity" name="severity" required>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="normal" selected>Normal</option>
              <option value="low">Low</option>
            </select>
          </div>
          <div class="form-item">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" required></textarea>
              </div>
              <div class="form-item">
                <label for="file_attachment">Attachment</label>
                <input type="file" id="file_attachment" name="file_attachment" accept="image/*,.pdf,.doc,.docx,.txt">
                <div class="form-item-description">Upload a screenshot or document related to this feedback (optional). Maximum size: 2MB.</div>
              </div>
              <input type="hidden" id="tidy-feedback-url" name="url" value="${window.location.href}">
              <input type="hidden" id="tidy-feedback-element-selector" name="element_selector" value="${elementSelector}">
              <input type="hidden" id="tidy-feedback-browser-info" name="browser_info" value="">
              <input type="hidden" name="form_token" id="form-token" value="">
          <div class="form-actions">
            <button type="submit" class="button button--primary">Submit Feedback</button>
            <button type="button" id="feedback-cancel" class="button">Cancel</button>
          </div>
        </form>
      </div>
    `;

    // Create modal container if needed
    if (!$("#tidy-feedback-modal").length) {
      $("body").append(
        '<div id="tidy-feedback-modal" class="tidy-feedback-ui"></div>',
      );
    }

    // Set the form content directly
    $("#tidy-feedback-modal").html(simpleForm);

    // Set proper browser info after the form is created
    setBrowserInfo();
    
    // Add file validation
    $("#file_attachment").on("change", function() {
      validateFileUpload(this);
    });

    // Create dialog
    var dialogElement = document.getElementById("tidy-feedback-modal");
    var dialogObj = Drupal.dialog(dialogElement, {
      title: Drupal.t("Submit Feedback"),
      width: "500px",
      dialogClass: "tidy-feedback-ui",
    });

    // Store dialog object as a jQuery data attribute for easy access
    $(dialogElement).data("drupalDialog", dialogObj);

    // Show the dialog
    dialogObj.showModal();

    // Get CSRF token and set it in the form
    getCsrfToken(function(token) {
      $("#form-token").val(token);
    });
    
    // Handle form submission
    $("#tidy-feedback-simple-form").on("submit", function (e) {
      e.preventDefault();
      
      // Clear any existing error messages
      $("#tidy-feedback-form-wrapper .messages--error").remove();
      
      // Validate the file first
      if ($('#file_attachment')[0].files.length > 0) {
        if (!validateFileUpload($('#file_attachment')[0])) {
          return false;
        }
      }
      
      // Check for required fields
      if (!$("#description").val()) {
        $("#tidy-feedback-form-wrapper").prepend(
          '<div class="messages messages--error">' + 
          Drupal.t('Description is required.') + 
          '</div>'
        );
        return false;
      }
      
      // Set browser info before submitting
      $("#tidy-feedback-browser-info").val(getBrowserInfo());
      
      // Show loading message
      $("#tidy-feedback-form-wrapper .messages--status").remove();
      $("#tidy-feedback-form-wrapper").prepend(
        '<div class="messages messages--status" id="tidy-feedback-loading">' + 
        Drupal.t('Submitting feedback...') + 
        '</div>'
      );
      
      // Create FormData object from the form
      var formData = new FormData(this);
      
      // Log form data for debugging
      debugFormData(formData);
      
      // Get CSRF token and submit via AJAX
      getCsrfToken(function(token) {
        $.ajax({
          url: Drupal.url("tidy-feedback/submit"),
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          headers: {
            'X-CSRF-Token': token
          },
          success: function(response) {
            console.log("Feedback submitted successfully:", response);
            
            // Remove loading message
            $("#tidy-feedback-loading").remove();
            
            // Close the dialog
            var dialogObj = $("#tidy-feedback-modal").data("drupalDialog");
            if (dialogObj && typeof dialogObj.close === "function") {
              dialogObj.close();
            } else {
              // Fallback method
              $(".ui-dialog-titlebar-close").click();
            }
            
            // Show success message
            showSuccessMessage();
          },
          error: function(xhr, status, error) {
            console.error("Error submitting feedback:", {
              status: status,
              error: error,
              response: xhr.responseText,
              statusCode: xhr.status
            });
            
            // Remove loading message
            $("#tidy-feedback-loading").remove();
            
            // Show error message
            var errorMessage = Drupal.t("Error submitting feedback. Please try again.");
            if (xhr.responseJSON && xhr.responseJSON.message) {
              errorMessage = xhr.responseJSON.message;
            }
            
            $("#tidy-feedback-form-wrapper").prepend(
              '<div class="messages messages--error">' + errorMessage + '</div>'
            );
          }
        });
      });
    });

    // Handle cancel button
    $("#feedback-cancel").on("click", function () {
      var dialogObj = $("#tidy-feedback-modal").data("drupalDialog");
      if (dialogObj && typeof dialogObj.close === "function") {
        dialogObj.close();
      } else {
        // Fallback method
        $(".ui-dialog-titlebar-close").click();
      }
    });

    // Deactivate feedback mode
    toggleFeedbackMode();
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
          // Store token in a data attribute for future use
          $('body').attr('data-csrf-token', token);
        },
        error: function(xhr, status, error) {
          console.error('Error getting CSRF token:', error);
        }
      });
    }
  }

  // Function to set browser information
  function setBrowserInfo() {
    // Set the value as a properly formatted JSON string
    $("#tidy-feedback-browser-info").val(getBrowserInfo());
  }
  
  // Function to get browser information as a JSON string
  function getBrowserInfo() {
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

    return JSON.stringify(browserInfo);
  }

  // Function to validate file uploads
  function validateFileUpload(fileInput) {
    const maxSize = 2 * 1024 * 1024; // 2MB
    const file = fileInput.files[0];
    
    // Clear previous error messages
    $(fileInput).next('.file-upload-error').remove();
    
    if (file) {
      // Check file size
      if (file.size > maxSize) {
        $(fileInput).after('<div class="file-upload-error">' + 
          Drupal.t('The file exceeds the maximum allowed size of 2MB.') + '</div>');
        fileInput.value = ''; // Clear the file input
        return false;
      }
      
      // Check file type (allow images, PDFs, docs, and text)
      const acceptableTypes = ['image/', '.pdf', '.doc', '.docx', '.txt'];
      let validType = false;
      
      for (const type of acceptableTypes) {
        if (file.type.includes(type) || file.name.toLowerCase().endsWith(type)) {
          validType = true;
          break;
        }
      }
      
      if (!validType) {
        $(fileInput).after('<div class="file-upload-error">' + 
          Drupal.t('Invalid file type. Please upload an image, PDF, Word document, or text file.') + '</div>');
        fileInput.value = ''; // Clear the file input
        return false;
      }
    }
    
    return true;
  }

  // Function to show success message
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
  
  // Debug helper to log FormData contents
  function debugFormData(formData) {
    console.log("FormData contents:");
    for (var pair of formData.entries()) {
      if (pair[0] === 'file_attachment') {
        console.log(pair[0] + ': File object', {
          name: pair[1].name,
          type: pair[1].type,
          size: pair[1].size
        });
      } else {
        console.log(pair[0] + ': ' + pair[1]);
      }
    }
  }
})(Drupal, drupalSettings, once);
