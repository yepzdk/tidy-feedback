<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * A simple test controller.
 */
class TidyFeedbackTestController extends ControllerBase {

  /**
   * Display a simple test page.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response with basic information.
   */
  public function testPage() {
    // Enable error display
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    $output = '<h1>Tidy Feedback Test Page</h1>';
    $output .= '<p>This is a simple test page to verify routing works.</p>';
    
    // PHP info
    $output .= '<h2>PHP Info</h2>';
    $output .= '<ul>';
    $output .= '<li>PHP Version: ' . phpversion() . '</li>';
    $output .= '<li>Memory Limit: ' . ini_get('memory_limit') . '</li>';
    $output .= '<li>Upload Max Filesize: ' . ini_get('upload_max_filesize') . '</li>';
    $output .= '<li>Post Max Size: ' . ini_get('post_max_size') . '</li>';
    $output .= '</ul>';
    
    // Simple file upload form
    $output .= '<h2>Simple Upload Test</h2>';
    $output .= '<form action="/tidy-feedback/simple-test-upload" method="post" enctype="multipart/form-data">';
    $output .= '<div><input type="file" name="test_file"></div>';
    $output .= '<div style="margin-top: 10px;"><button type="submit">Test Upload</button></div>';
    $output .= '</form>';
    
    return new Response($output);
  }
  
  /**
   * Handle a simple file upload.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response with upload results.
   */
  public function handleTestUpload() {
    $output = '<h1>Upload Test Results</h1>';
    
    $output .= '<h2>$_FILES Contents</h2>';
    $output .= '<pre>' . print_r($_FILES, TRUE) . '</pre>';
    
    $output .= '<p><a href="/tidy-feedback/test">Back to test page</a></p>';
    
    return new Response($output);
  }

}