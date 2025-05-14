<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\file\Entity\File;

/**
 * Simple controller for debugging Tidy Feedback file uploads.
 */
class TidyFeedbackBasicDebugController extends ControllerBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new TidyFeedbackBasicDebugController.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * Display a simple debug page.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   A response object or render array.
   */
  public function debugPage() {
    // Enable detailed error output
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    $output = '<h1>Tidy Feedback Debug Page</h1>';
    
    // PHP Configuration
    $output .= '<h2>PHP Configuration</h2>';
    $output .= '<ul>';
    $output .= '<li>PHP Version: ' . phpversion() . '</li>';
    $output .= '<li>Memory Limit: ' . ini_get('memory_limit') . '</li>';
    $output .= '<li>Max Execution Time: ' . ini_get('max_execution_time') . ' seconds</li>';
    $output .= '<li>Upload Max Filesize: ' . ini_get('upload_max_filesize') . '</li>';
    $output .= '<li>Post Max Size: ' . ini_get('post_max_size') . '</li>';
    $output .= '<li>Max File Uploads: ' . ini_get('max_file_uploads') . '</li>';
    $output .= '<li>File Uploads Enabled: ' . (ini_get('file_uploads') ? 'Yes' : 'No') . '</li>';
    $output .= '<li>Upload Tmp Dir: ' . (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()) . '</li>';
    
    // Check if tmp directory is writable
    $tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
    $output .= '<li>Temp Directory Writable: ' . (is_writable($tmpDir) ? 'Yes' : 'No') . '</li>';
    
    // Server software
    $output .= '<li>Server Software: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '</li>';
    $output .= '</ul>';
    
    // Directory Info
    $output .= '<h2>Directory Information</h2>';
    
    $directories = [
      'public://' => 'Public Files',
      'public://tidy_feedback' => 'Tidy Feedback',
      'public://tidy_feedback/attachments' => 'Attachments',
      '/tmp' => 'System Temp Directory',
    ];
    
    $output .= '<table border="1" cellpadding="5" cellspacing="0">';
    $output .= '<tr><th>Directory</th><th>Real Path</th><th>Exists</th><th>Writable</th><th>Actions</th></tr>';
    
    foreach ($directories as $dir => $label) {
      if ($dir == '/tmp') {
        $real_path = $dir;
      } else {
        $real_path = $this->fileSystem->realpath($dir);
      }
      $exists = $real_path && file_exists($real_path);
      $writable = $exists && is_writable($real_path);
      
      $output .= '<tr>';
      $output .= '<td>' . $label . '</td>';
      $output .= '<td>' . ($real_path ?: 'Not found') . '</td>';
      $output .= '<td>' . ($exists ? 'Yes' : 'No') . '</td>';
      $output .= '<td>' . ($writable ? 'Yes' : 'No') . '</td>';
      $output .= '<td>';
      
      if (!$exists) {
        $output .= '<a href="/tidy-feedback/basic-debug/create-dir?path=' . urlencode($dir) . '">Create</a>';
      } else {
        $output .= '<a href="/tidy-feedback/basic-debug/fix-permissions?path=' . urlencode($dir) . '">Fix Permissions</a>';
      }
      
      $output .= '</td></tr>';
    }
    
    $output .= '</table>';
    
    // Test upload forms with different methods
    $output .= '<h2>Test File Upload Methods</h2>';
    
    // Standard form
    $output .= '<h3>Standard Form</h3>';
    $output .= '<form action="/tidy-feedback/basic-debug/upload" method="post" enctype="multipart/form-data">';
    $output .= '<div><label for="test_file">Select a file:</label></div>';
    $output .= '<div><input type="file" name="test_file" id="test_file"></div>';
    $output .= '<div style="margin-top: 10px;"><button type="submit">Upload Test File</button></div>';
    $output .= '</form>';
    
    // Simple tiny form with minimal markup
    $output .= '<h3>Minimal Form</h3>';
    $output .= '<form action="/tidy-feedback/basic-debug/upload" method="post" enctype="multipart/form-data">';
    $output .= '<input type="file" name="test_file"> ';
    $output .= '<button type="submit">Upload</button>';
    $output .= '</form>';
    
    // Direct HTML form
    $output .= '<h3>Direct HTML Form (no Drupal processing)</h3>';
    $output .= '<form action="/tidy-feedback/basic-debug/upload" method="post" enctype="multipart/form-data" id="direct-form">';
    $output .= '<input type="file" name="test_file"> ';
    $output .= '<button type="submit">Upload</button>';
    $output .= '</form>';
    
    // Additional debug information
    $output .= '<h2>Additional Debug Information</h2>';
    
    // Try to detect server-imposed limits
    $serverMaxPost = $this->getServerMaxPostSize();
    $output .= '<p>Estimated server max post size: ' . $serverMaxPost . '</p>';
    
    $output .= '<h2>Browser Information</h2>';
    $output .= '<div id="browser-info"></div>';
    
    // Add JavaScript to get browser info and enhance forms
    $output .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            try {
                var browserInfo = {
                    userAgent: navigator.userAgent,
                    platform: navigator.platform,
                    vendor: navigator.vendor,
                    language: navigator.language
                };
                
                var infoHtml = "<ul>";
                for (var key in browserInfo) {
                    infoHtml += "<li><strong>" + key + ":</strong> " + browserInfo[key] + "</li>";
                }
                infoHtml += "</ul>";
                
                document.getElementById("browser-info").innerHTML = infoHtml;
                
                // Add file selection info
                var fileInputs = document.querySelectorAll("input[type=file]");
                fileInputs.forEach(function(input) {
                    input.addEventListener("change", function() {
                        if (this.files.length > 0) {
                            var fileInfo = document.createElement("p");
                            fileInfo.innerHTML = "<strong>Selected file:</strong> " + 
                                this.files[0].name + " (" + Math.round(this.files[0].size/1024) + " KB)";
                            this.parentNode.appendChild(fileInfo);
                        }
                    });
                });
            } catch (error) {
                console.error("Error getting browser info:", error);
                document.getElementById("browser-info").innerHTML = "<p>Error getting browser info: " + error.message + "</p>";
            }
        });
    </script>';
    
    // Add some CSS for better readability
    $output = '
      <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2, h3 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        .error { color: #cc0000; }
        ul { margin-bottom: 20px; }
        form { margin-bottom: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; }
      </style>
    ' . $output;
    
    return new Response($output);
  }
  
  /**
   * Estimate the server's max post size.
   *
   * @return string
   *   The estimated max post size.
   */
  private function getServerMaxPostSize() {
    $post_max = ini_get('post_max_size');
    $upload_max = ini_get('upload_max_filesize');
    
    // Convert to bytes for comparison
    $post_bytes = $this->returnBytes($post_max);
    $upload_bytes = $this->returnBytes($upload_max);
    
    // Return the smaller of the two
    if ($post_bytes < $upload_bytes) {
      return $post_max . ' (from post_max_size)';
    } else {
      return $upload_max . ' (from upload_max_filesize)';
    }
  }
  
  /**
   * Convert PHP size strings to bytes.
   *
   * @param string $size_str
   *   The size string (e.g., "2M", "8M").
   *
   * @return int
   *   The size in bytes.
   */
  private function returnBytes($size_str) {
    switch (substr($size_str, -1)) {
      case 'K':
      case 'k':
        return (int) $size_str * 1024;
      case 'M':
      case 'm':
        return (int) $size_str * 1048576;
      case 'G':
      case 'g':
        return (int) $size_str * 1073741824;
      default:
        return (int) $size_str;
    }
  }
  
  /**
   * Create a directory.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A redirect response.
   */
  public function createDirectory(Request $request) {
    $path = $request->query->get('path');
    
    if (!empty($path)) {
      try {
        $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $this->messenger()->addStatus($this->t('Directory created successfully: @path', ['@path' => $path]));
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error creating directory: @error', ['@error' => $e->getMessage()]));
      }
    }
    
    return $this->redirect('tidy_feedback.basic_debug');
  }
  
  /**
   * Fix directory permissions.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A redirect response.
   */
  public function fixPermissions(Request $request) {
    $path = $request->query->get('path');
    
    if (!empty($path)) {
      try {
        $real_path = $this->fileSystem->realpath($path);
        
        if ($real_path && file_exists($real_path)) {
          chmod($real_path, 0777);
          $this->messenger()->addStatus($this->t('Directory permissions fixed: @path', ['@path' => $real_path]));
        }
        else {
          $this->messenger()->addError($this->t('Directory not found: @path', ['@path' => $path]));
        }
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error fixing permissions: @error', ['@error' => $e->getMessage()]));
      }
    }
    
    return $this->redirect('tidy_feedback.basic_debug');
  }
  
  /**
   * Handle file upload test.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   */
  public function handleUpload(Request $request) {
    // Enable maximum error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    $output = '<h1>File Upload Test Results</h1>';
    
    // Check server variables first
    $output .= '<h2>Server Environment</h2>';
    $output .= '<ul>';
    $output .= '<li>Server software: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '</li>';
    $output .= '<li>PHP version: ' . PHP_VERSION . '</li>';
    $output .= '<li>Request method: ' . $_SERVER['REQUEST_METHOD'] . '</li>';
    $output .= '<li>Content type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'Not set') . '</li>';
    $output .= '<li>Content length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'Not set') . '</li>';
    $output .= '</ul>';
    
    // Check upload specific configuration
    $output .= '<h2>Upload Configuration</h2>';
    $output .= '<ul>';
    $output .= '<li>upload_max_filesize: ' . ini_get('upload_max_filesize') . '</li>';
    $output .= '<li>post_max_size: ' . ini_get('post_max_size') . '</li>';
    $output .= '<li>file_uploads enabled: ' . (ini_get('file_uploads') ? 'Yes' : 'No') . '</li>';
    $output .= '<li>upload_tmp_dir: ' . (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()) . '</li>';
    
    // Check if the temporary directory is writable
    $tmpDir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
    $output .= '<li>Temp directory writable: ' . (is_writable($tmpDir) ? 'Yes' : 'No') . '</li>';
    $output .= '</ul>';
    
    // Dump raw $_FILES and $_POST arrays
    $output .= '<h2>Raw Upload Data</h2>';
    $output .= '<h3>$_FILES</h3>';
    $output .= '<pre>' . print_r($_FILES, TRUE) . '</pre>';
    
    $output .= '<h3>$_POST</h3>';
    $output .= '<pre>' . print_r($_POST, TRUE) . '</pre>';
    
    try {
      $files = $request->files->get('test_file');
      
      if (empty($files)) {
        $output .= '<h2>No File Uploaded</h2>';
        $output .= '<p>The request did not contain any file information.</p>';
        
        // Check for common issues
        if (empty($_FILES)) {
          $output .= '<p class="error">The $_FILES array is empty. This could indicate:</p>';
          $output .= '<ul>';
          $output .= '<li>The form is missing enctype="multipart/form-data"</li>';
          $output .= '<li>The file upload was blocked by server configuration</li>';
          $output .= '<li>The file exceeded the maximum allowed size (' . ini_get('upload_max_filesize') . ')</li>';
          $output .= '<li>The POST request exceeded the maximum allowed size (' . ini_get('post_max_size') . ')</li>';
          $output .= '</ul>';
        }
      }
      else {
        $output .= '<h2>File Details from Request Object</h2>';
        $output .= '<ul>';
        $output .= '<li>Original name: ' . $files->getClientOriginalName() . '</li>';
        $output .= '<li>File size: ' . $files->getSize() . ' bytes</li>';
        $output .= '<li>MIME type: ' . $files->getClientMimeType() . '</li>';
        $output .= '<li>Error code: ' . $files->getError() . '</li>';
        $output .= '<li>Is valid: ' . ($files->isValid() ? 'Yes' : 'No') . '</li>';
        $output .= '<li>Path to uploaded file: ' . $files->getPathname() . '</li>';
        $output .= '<li>File exists: ' . (file_exists($files->getPathname()) ? 'Yes' : 'No') . '</li>';
        $output .= '</ul>';
        
        // Save the file
        if ($files->isValid()) {
          // Create directory
          $directory = 'public://tidy_feedback/attachments';
          $result = $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
          $output .= '<p>Directory preparation result: ' . ($result ? 'Success' : 'Failed') . '</p>';
          
          // Get real path of target directory
          $realDir = $this->fileSystem->realpath($directory);
          $output .= '<p>Target directory real path: ' . ($realDir ?: 'Could not resolve') . '</p>';
          $output .= '<p>Target directory writable: ' . (is_writable($realDir) ? 'Yes' : 'No') . '</p>';
          
          // Try a direct PHP copy first for debugging
          $tempPath = $files->getPathname();
          $destPath = $realDir . '/' . $files->getClientOriginalName();
          $directCopyResult = @copy($tempPath, $destPath);
          $output .= '<p>Direct PHP copy result: ' . ($directCopyResult ? 'Success' : 'Failed') . '</p>';
          
          // If direct copy failed, show error info
          if (!$directCopyResult) {
            $output .= '<p>Copy error: ' . error_get_last()['message'] . '</p>';
          }
          
          // Save the file using Drupal methods
          try {
            $destination = $directory . '/' . $files->getClientOriginalName();
            $file_uri = $this->fileSystem->saveUploadedFile($files, $destination, FileSystemInterface::EXISTS_RENAME);
            
            if ($file_uri) {
              $output .= '<p>File saved successfully to: ' . $file_uri . '</p>';
              $output .= '<p>Real path: ' . $this->fileSystem->realpath($file_uri) . '</p>';
              
              // Create file entity
              $file = File::create([
                'uri' => $file_uri,
                'uid' => $this->currentUser()->id(),
                'status' => FILE_STATUS_PERMANENT,
                'filename' => $this->fileSystem->basename($file_uri),
              ]);
              $file->save();
              
              $output .= '<p>File entity created with ID: ' . $file->id() . '</p>';
            }
            else {
              $output .= '<p class="error">Error saving the file using Drupal methods.</p>';
            }
          }
          catch (\Exception $e) {
            $output .= '<p class="error">Exception in saveUploadedFile: ' . $e->getMessage() . '</p>';
            $output .= '<pre>' . $e->getTraceAsString() . '</pre>';
          }
        }
        else {
          $output .= '<h2 class="error">File Upload Error</h2>';
          
          // Provide error message based on error code
          $errors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
          ];
          
          if (isset($errors[$files->getError()])) {
            $output .= '<p>Error: ' . $errors[$files->getError()] . '</p>';
          }
        }
      }
    }
    catch (\Exception $e) {
      $output .= '<h2 class="error">Exception Caught</h2>';
      $output .= '<p>' . $e->getMessage() . '</p>';
      $output .= '<pre>' . $e->getTraceAsString() . '</pre>';
    }
    
    // Add some CSS for better readability
    $output = '
      <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2, h3 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
        .error { color: #cc0000; }
        ul { margin-bottom: 20px; }
      </style>
    ' . $output;
    
    $output .= '<p><a href="/tidy-feedback/basic-debug">Back to debug page</a></p>';
    
    return new Response($output);
  }
}