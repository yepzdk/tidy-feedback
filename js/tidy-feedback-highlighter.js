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
    console.log("Opening feedback modal for element:", elementSelector);
    
    // Create a simple form with the element selector
    var formHtml = `
      <div class="tidy-feedback-form-container">
        <form id="tidy-feedback-form" action="/tidy-feedback/simple-submit" method="post" enctype="multipart/form-data">
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
            <label for="attachment">Attachment</label>
            <input type="file" id="attachment" name="files[attachment]" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">
            <div class="description">Upload a file to provide additional context (optional).</div>
          </div>
          
          <input type="hidden" id="tidy-feedback-url" name="url" value="${window.location.href}">
          <input type="hidden" id="tidy-feedback-element-selector" name="element_selector" value="${elementSelector}">
          <input type="hidden" id="tidy-feedback-browser-info" name="browser_info" value="">
          
          <div class="form-actions">
            <button type="submit" id="tidy-feedback-submit" class="button button--primary">Submit Feedback</button>
            <button type="button" id="tidy-feedback-cancel" class="button">Cancel</button>
          </div>
        </form>
      </div>
    `;
    
    // Create modal container if needed
    if (!$("#tidy-feedback-modal").length) {
      $("body").append('<div id="tidy-feedback-modal" class="tidy-feedback-ui"></div>');
    }
    
    // Set the form content 
    $("#tidy-feedback-modal").html(formHtml);
    
    // Pre-fill browser info
    setBrowserInfoField();
    
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
    
    // Add submit handler
    $("#tidy-feedback-form").on("submit", function(e) {
      e.preventDefault();
      
      var formData = new FormData(this);
      
      // Submit the form via AJAX
      $.ajax({
        url: $(this).attr('action'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
          // Close the dialog
          dialogObj.close();
          
          // Show success message
          showSuccessMessage();
        },
        error: function(xhr, status, error) {
          console.error("Error submitting form:", error);
          alert("Error submitting feedback. Please try again.");
        }
      });
    });
    
    // Add cancel button handler
    $("#tidy-feedback-cancel").on("click", function() {
      dialogObj.close();
    });
    
    // Deactivate feedback mode
    toggleFeedbackMode();
  }

  /**
   * Sets browser information in the hidden input field.
   */
  function setBrowserInfoField() {
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
   * Function to show success message
   */
  function showSuccessMessage() {
    const message = $('<div class="tidy-feedback-success-message"></div>')
      .text(Drupal.t("Feedback submitted successfully"))
      .appendTo("body");

    // Add styles to make the message more visible
    message.css({
      'position': 'fixed',
      'top': '20px',
      'right': '20px',
      'background-color': '#4CAF50',
      'color': 'white',
      'padding': '15px 20px',
      'border-radius': '4px',
      'box-shadow': '0 2px 5px rgba(0,0,0,0.2)',
      'z-index': '9999'
    });

    setTimeout(function () {
      message.fadeOut(400, function () {
        $(this).remove();
      });
      
      // Redirect to the feedback list after showing the message
      setTimeout(function() {
        window.location.href = '/admin/reports/tidy-feedback';
      }, 1000);
    }, 2000);
  }
})(Drupal, drupalSettings, once);
