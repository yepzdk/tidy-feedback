/**
 * @file
 * JavaScript for the Tidy Feedback module using direct form.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  console.log('Tidy Feedback Direct script loaded');

  // Create and append the feedback banner to the page
  function createFeedbackBanner() {
    console.log('Creating feedback banner');
    let $banner = $('.tidy-feedback-banner');
    
    // Only create if it doesn't exist
    if ($banner.length === 0) {
      $banner = $('<div class="tidy-feedback-banner"><button class="tidy-feedback-button">Provide Feedback</button></div>');
      $('body').append($banner);
      
      // Attach click handler directly
      $banner.on('click', function(e) {
        console.log('Banner clicked');
        e.preventDefault();
        toggleSelectMode();
      });
    }
    
    return $banner;
  }

  // Global variables
  let selectMode = false;
  let highlightOverlay = null;
  let hoveredElement = null;

  // Toggle element selection mode
  function toggleSelectMode() {
    if (selectMode) {
      exitSelectMode();
    } else {
      enterSelectMode();
    }
  }

  // Create the highlight overlay
  function createHighlight() {
    highlightOverlay = $('<div class="tidy-feedback-highlight"></div>');
    $('body').append(highlightOverlay);
  }

  // Update highlight position based on hovered element
  function updateHighlight(element) {
    if (!element || !highlightOverlay) return;
    
    const $element = $(element);
    const offset = $element.offset();
    const width = $element.outerWidth();
    const height = $element.outerHeight();
    
    highlightOverlay.css({
      top: offset.top + 'px',
      left: offset.left + 'px',
      width: width + 'px',
      height: height + 'px'
    });
  }

  // Generate a CSS selector for an element
  function generateSelector(element) {
    const $element = $(element);
    let selector = '';
    
    // Try to get id
    if ($element.attr('id')) {
      return '#' + $element.attr('id');
    }
    
    // Try with classes
    if ($element.attr('class')) {
      const classes = $element.attr('class').split(' ').filter(Boolean);
      if (classes.length > 0) {
        selector = classes.map(c => '.' + c).join('');
      }
    }
    
    // Fallback to tag name
    if (!selector) {
      selector = $element.prop('tagName').toLowerCase();
    }
    
    // Add parent context
    let $parent = $element.parent();
    let parentSelector = '';
    
    if ($parent.attr('id')) {
      parentSelector = '#' + $parent.attr('id');
    } else if ($parent.attr('class')) {
      const parentClasses = $parent.attr('class').split(' ').filter(Boolean);
      if (parentClasses.length > 0) {
        parentSelector = parentClasses.map(c => '.' + c).join('');
      }
    } else {
      parentSelector = $parent.prop('tagName').toLowerCase();
    }
    
    if (parentSelector) {
      selector = parentSelector + ' > ' + selector;
    }
    
    return selector;
  }

  // Enter element selection mode
  function enterSelectMode() {
    console.log('Entering selection mode');
    selectMode = true;
    $('body').addClass('tidy-feedback-select-mode');
    createHighlight();
    
    // Show helper message
    const helper = $('<div class="tidy-feedback-helper">Click on an element to provide feedback about it, or press ESC to cancel.</div>');
    $('body').append(helper);
    
    // Change cursor style
    $('body').css('cursor', 'crosshair');
    
    // Add mouseover event handler for highlighting
    $(document).on('mouseover.tidyfeedback', '*', handleMouseover);
    
    // Add click event handler for selection
    $(document).on('click.tidyfeedback', '*', handleElementClick);
    
    // Add ESC key handler
    $(document).on('keyup.tidyfeedback', handleKeypress);
  }

  // Exit element selection mode
  function exitSelectMode() {
    console.log('Exiting selection mode');
    selectMode = false;
    $('body').removeClass('tidy-feedback-select-mode');
    
    if (highlightOverlay) {
      highlightOverlay.remove();
      highlightOverlay = null;
    }
    
    $('.tidy-feedback-helper').remove();
    $('body').css('cursor', '');
    
    // Remove event handlers
    $(document).off('mouseover.tidyfeedback');
    $(document).off('click.tidyfeedback');
    $(document).off('keyup.tidyfeedback');
  }

  // Handle mouseover for highlighting elements
  function handleMouseover(e) {
    // Ignore the highlight overlay and feedback elements
    if ($(this).hasClass('tidy-feedback-highlight') || 
        $(this).hasClass('tidy-feedback-banner') ||
        $(this).hasClass('tidy-feedback-helper') ||
        $(this).hasClass('tidy-feedback-button')) {
      return;
    }
    
    e.stopPropagation();
    hoveredElement = this;
    updateHighlight(hoveredElement);
  }

  // Handle element click for selection
  function handleElementClick(e) {
    // Ignore the highlight overlay and feedback elements
    if ($(this).hasClass('tidy-feedback-highlight') || 
        $(this).hasClass('tidy-feedback-banner') ||
        $(this).hasClass('tidy-feedback-helper') ||
        $(this).hasClass('tidy-feedback-button')) {
      return;
    }
    
    e.preventDefault();
    e.stopPropagation();
    
    const selector = generateSelector(this);
    const currentUrl = window.location.href;
    
    console.log('Element selected', { selector, currentUrl });
    
    // Exit select mode
    exitSelectMode();
    
    // Open the direct feedback form in a new window/tab
    const feedbackUrl = `/tidy-feedback/direct-form?element_selector=${encodeURIComponent(selector)}&url=${encodeURIComponent(currentUrl)}`;
    console.log('Opening feedback form at', feedbackUrl);
    window.open(feedbackUrl, '_blank');
  }

  // Handle keypress for ESC key
  function handleKeypress(e) {
    if (e.key === 'Escape') {
      exitSelectMode();
    }
  }

  // Drupal behavior to initialize feedback banner
  Drupal.behaviors.tidyFeedbackDirect = {
    attach: function (context, settings) {
      // Run only once per page load
      if (context === document) {
        console.log('Tidy Feedback Direct behavior attached to document');
        // Create the banner when the document is ready
        $(document).ready(function() {
          createFeedbackBanner();
        });
      }
    }
  };

})(jQuery, Drupal, drupalSettings);