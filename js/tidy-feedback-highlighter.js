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
        console.log("Tidy Feedback highlighter initialized");

        // Create the banner if it doesn't exist
        if (!$(".tidy-feedback-banner").length) {
          console.log("Creating banner");
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
          console.log("Attaching click handler to banner");
          $(banner).on("click", function (e) {
            console.log("Banner clicked");
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
    console.log("Feedback mode:", feedbackModeActive ? "ON" : "OFF");

    // Toggle active class on banner
    $(".tidy-feedback-banner").toggleClass("active", feedbackModeActive);

    if (feedbackModeActive) {
      // Create overlay if it doesn't exist
      if (!$("#tidy-feedback-overlay").length) {
        $("body").append(
          '<div id="tidy-feedback-overlay" class="tidy-feedback-ui"></div>',
        );
        console.log("Overlay created");
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

      console.log("Feedback mode activated");
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

      console.log("Feedback mode deactivated");
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
    console.log("Clicked on element:", elementSelector);

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
    console.log("Opening feedback modal for:", elementSelector);

    // Create a simple form without relying on Drupal's form API
    var simpleForm = `
      <div id="tidy-feedback-form-wrapper">
        <form id="tidy-feedback-simple-form">
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
          <input type="hidden" id="tidy-feedback-url" name="url" value="${window.location.href}">
          <input type="hidden" id="tidy-feedback-element-selector" name="element_selector" value="${elementSelector}">
          <input type="hidden" id="tidy-feedback-browser-info" name="browser_info" value="">
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

    // Handle form submission
    $("#tidy-feedback-simple-form").on("submit", function (e) {
      e.preventDefault();
      console.log("Form submitted");

      // Collect form data
      var formData = {
        issue_type: $("#issue_type").val(),
        severity: $("#severity").val(),
        description: $("#description").val(),
        url: $("#tidy-feedback-url").val(),
        element_selector: $("#tidy-feedback-element-selector").val(),
        browser_info: $("#tidy-feedback-browser-info").val(),
      };

      console.log("Submitting data:", formData);

      // Manual AJAX submission
      $.ajax({
        url: Drupal.url("tidy-feedback/submit"),
        type: "POST",
        data: JSON.stringify(formData),
        contentType: "application/json",
        dataType: "json",
        success: function (response) {
          console.log("Submission successful:", response);
          // Close dialog properly using the stored reference
          var dialogObj = $("#tidy-feedback-modal").data("drupalDialog");
          if (dialogObj && typeof dialogObj.close === "function") {
            dialogObj.close();
          } else {
            // Fallback method if dialog object isn't available
            $(".ui-dialog-titlebar-close").click();
          }
          showSuccessMessage();
        },
        error: function (xhr, status, error) {
          console.error("Submission error:", error);
          $("#tidy-feedback-form-wrapper").prepend(
            '<div class="messages messages--error">' +
              Drupal.t("Error submitting feedback. Please try again.") +
              "</div>",
          );
        },
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

  // Function to set browser information
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

  // Debug form submission
  $(document).on("submit", "#tidy-feedback-form", function () {
    console.log("Form submit detected");
  });

  // Debug AJAX events
  $(document).ajaxSend(function (event, jqxhr, settings) {
    console.log("AJAX request sent:", settings.url);
  });

  $(document).ajaxSuccess(function (event, jqxhr, settings) {
    console.log("AJAX request successful:", settings.url);
  });

  $(document).ajaxError(function (event, jqxhr, settings, error) {
    console.log("AJAX request failed:", settings.url, error);
  });
})(Drupal, drupalSettings, once);
