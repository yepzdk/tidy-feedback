<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a bare-bones file upload test page.
 */
class TidyFeedbackSimpleUploadController extends ControllerBase {

  /**
   * Displays a simple file upload test page.
   */
  public function testPage() {
    // Enable maximum error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    $output = '<!DOCTYPE html>
<html>
<head>
    <title>Simple File Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 10px; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Simple File Upload Test</h1>
    
    <form action="/tidy-feedback/simple-upload/process" method="post" enctype="multipart/form-data">
        <p>
            <label>
                Select File: 
                <input type="file" name="test_file">
            </label>
        </p>
        <p>
            <button type="submit">Upload File</button>
        </p>
    </form>
    
    <h2>PHP Info</h2>
    <ul>
        <li>PHP Version: ' . phpversion() . '</li>
        <li>upload_max_filesize: ' . ini_get('upload_max_filesize') . '</li>
        <li>post_max_size: ' . ini_get('post_max_size') . '</li>
        <li>file_uploads enabled: ' . (ini_get('file_uploads') ? 'Yes' : 'No') . '</li>
        <li>tmp_dir: ' . (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()) . '</li>
    </ul>
</body>
</html>';

    return new Response($output);
  }
  
  /**
   * Processes the file upload.
   */
  public function processUpload() {
    // Enable maximum error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    $output = '<!DOCTYPE html>
<html>
<head>
    <title>Upload Result</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 10px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Upload Result</h1>';
    
    // Check if we have files
    if (empty($_FILES)) {
      $output .= '<p class="error">No files were uploaded. The $_FILES array is empty.</p>';
      $output .= '<p>This usually indicates:</p>
        <ul>
            <li>The form might be missing enctype="multipart/form-data"</li>
            <li>The file might exceed the maximum allowed size</li>
            <li>There might be a server configuration issue blocking file uploads</li>
        </ul>';
    }
    else {
      $output .= '<h2>$_FILES Contents</h2>';
      $output .= '<pre>' . print_r($_FILES, TRUE) . '</pre>';
      
      // Check for the test file
      if (isset($_FILES['test_file'])) {
        $file = $_FILES['test_file'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
          $output .= '<p class="success">File uploaded successfully!</p>';
          $output .= '<ul>
              <li>Name: ' . htmlspecialchars($file['name']) . '</li>
              <li>Type: ' . htmlspecialchars($file['type']) . '</li>
              <li>Size: ' . $file['size'] . ' bytes</li>
              <li>Temp file: ' . htmlspecialchars($file['tmp_name']) . '</li>
          </ul>';
          
          // Try to save to Drupal files directory
          $destination = 'sites/default/files/test_upload_' . time() . '_' . $file['name'];
          
          if (move_uploaded_file($file['tmp_name'], $destination)) {
            $output .= '<p class="success">File saved to: ' . htmlspecialchars($destination) . '</p>';
          }
          else {
            $output .= '<p class="error">Failed to save the file to: ' . htmlspecialchars($destination) . '</p>';
            $output .= '<p>Error: ' . error_get_last()['message'] . '</p>';
          }
        }
        else {
          // Error handling
          $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
          ];
          
          $errorMessage = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : 'Unknown upload error';
          
          $output .= '<p class="error">Upload failed: ' . $errorMessage . ' (Error code: ' . $file['error'] . ')</p>';
        }
      }
      else {
        $output .= '<p class="error">The test_file field was not found in the upload.</p>';
      }
    }
    
    $output .= '<p><a href="/tidy-feedback/simple-upload/test">Back to upload form</a></p>
</body>
</html>';

    return new Response($output);
  }
}