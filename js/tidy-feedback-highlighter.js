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

  // Function to open feedback form
  function openFeedbackModal(elementSelector) {
    console.log("Opening feedback form for element:", elementSelector);
    
    // Deactivate feedback mode
    toggleFeedbackMode();
    
    // Properly encode the parameters for the URL
    var encodedSelector = encodeURIComponent(elementSelector);
    var encodedUrl = encodeURIComponent(window.location.href);
    
    // Log the URL we're about to redirect to
    console.log("Redirecting to feedback form with element:", elementSelector);
    
    // Use Drupal's URL function to construct the proper URL
    const formUrl = Drupal.url('tidy-feedback/feedback-form');
    window.location.href = formUrl + '?element_selector=' + encodedSelector + '&url=' + encodedUrl;
  }

  // No longer needed as we're using a dedicated page instead of a modal
})(Drupal, drupalSettings, once);
