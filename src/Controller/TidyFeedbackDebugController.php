<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for debugging Tidy Feedback issues.
 */
class TidyFeedbackDebugController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new TidyFeedbackDebugController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(Connection $database, FileSystemInterface $file_system) {
    $this->database = $database;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_system')
    );
  }

  /**
   * Display debug information about file uploads.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response with debug information.
   */
  public function debugFileUploads() {
    // Enable error display for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    $output = '<h1>Tidy Feedback File Upload Debugging</h1>';
    
    // Check PHP configuration
    $output .= '<h2>PHP Upload Configuration</h2>';
    $output .= '<ul>';
    $output .= '<li>file_uploads: ' . ini_get('file_uploads') . '</li>';
    $output .= '<li>upload_max_filesize: ' . ini_get('upload_max_filesize') . '</li>';
    $output .= '<li>post_max_size: ' . ini_get('post_max_size') . '</li>';
    $output .= '<li>max_file_uploads: ' . ini_get('max_file_uploads') . '</li>';
    $output .= '<li>upload_tmp_dir: ' . ini_get('upload_tmp_dir') . '</li>';
    $output .= '<li>Temporary system directory: ' . sys_get_temp_dir() . '</li>';
    $output .= '</ul>';
    
    // Check file upload directories
    $output .= '<h2>File Upload Directories</h2>';
    $output .= '<ul>';
    
    // Check public directory
    $public_path = 'public://';
    $real_public_path = $this->fileSystem->realpath($public_path);
    $output .= '<li>Public directory: ' . $real_public_path;
    $output .= '<ul>';
    $output .= '<li>Exists: ' . (file_exists($real_public_path) ? 'Yes' : 'No') . '</li>';
    $output .= '<li>Writable: ' . (is_writable($real_public_path) ? 'Yes' : 'No') . '</li>';
    $output .= '</ul></li>';
    
    // Check the tidy_feedback directory
    $tidy_path = 'public://tidy_feedback';
    $real_tidy_path = $this->fileSystem->realpath($tidy_path);
    $output .= '<li>Tidy feedback directory: ' . $real_tidy_path;
    $output .= '<ul>';
    $output .= '<li>Exists: ' . (file_exists($real_tidy_path) ? 'Yes' : 'No') . '</li>';
    
    if (!file_exists($real_tidy_path)) {
      // Try to create it
      $this->fileSystem->prepareDirectory($tidy_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $output .= '<li>Created: ' . (file_exists($real_tidy_path) ? 'Yes' : 'No') . '</li>';
    }
    
    $output .= '<li>Writable: ' . (is_writable($real_tidy_path) ? 'Yes' : 'No') . '</li>';
    $output .= '</ul></li>';
    
    // Check the attachments directory
    $attachments_path = 'public://tidy_feedback/attachments';
    $real_attachments_path = $this->fileSystem->realpath($attachments_path);
    $output .= '<li>Attachments directory: ' . $real_attachments_path;
    $output .= '<ul>';
    $output .= '<li>Exists: ' . (file_exists($real_attachments_path) ? 'Yes' : 'No') . '</li>';
    
    if (!file_exists($real_attachments_path)) {
      // Try to create it
      $this->fileSystem->prepareDirectory($attachments_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $output .= '<li>Created: ' . (file_exists($real_attachments_path) ? 'Yes' : 'No') . '</li>';
    }
    
    $output .= '<li>Writable: ' . (is_writable($real_attachments_path) ? 'Yes' : 'No') . '</li>';
    $output .= '</ul></li>';
    
    $output .= '</ul>';
    
    // Add a file upload test form
    $output .= '<h2>Test File Upload</h2>';
    $output .= '<form action="/tidy-feedback/debug-file-upload-handler" method="post" enctype="multipart/form-data">';
    $output .= '<div><label for="test_file">Select a file:</label></div>';
    $output .= '<div><input type="file" name="test_file" id="test_file"></div>';
    $output .= '<div style="margin-top: 10px;"><button type="submit">Upload Test File</button></div>';
    $output .= '</form>';
    
    // Add a database check section
    $output .= '<h2>Database Check</h2>';
    
    try {
      // Check if the tidy_feedback table exists
      $table_exists = $this->database->schema()->tableExists('tidy_feedback');
      $output .= '<p>Tidy feedback table exists: ' . ($table_exists ? 'Yes' : 'No') . '</p>';
      
      if ($table_exists) {
        // Get the structure of the table
        $fields = $this->database->schema()->getFieldInfo('tidy_feedback');
        $output .= '<h3>Table Structure</h3>';
        $output .= '<pre>' . print_r($fields, TRUE) . '</pre>';
        
        // Count the records
        $count = $this->database->select('tidy_feedback', 'tf')
          ->countQuery()
          ->execute()
          ->fetchField();
        $output .= '<p>Number of feedback records: ' . $count . '</p>';
        
        // Recent submissions with attachment info
        $output .= '<h3>Recent Submissions</h3>';
        $output .= '<table border="1" cellpadding="5" cellspacing="0">';
        $output .= '<tr><th>ID</th><th>Created</th><th>Description</th><th>Attachment</th></tr>';
        
        $recent = $this->database->select('tidy_feedback', 'tf')
          ->fields('tf', ['id', 'created', 'description__value', 'attachment__target_id'])
          ->orderBy('created', 'DESC')
          ->range(0, 10)
          ->execute();
        
        foreach ($recent as $record) {
          $date = date('Y-m-d H:i:s', $record->created);
          $desc = substr($record->description__value, 0, 50) . (strlen($record->description__value) > 50 ? '...' : '');
          $attachment = $record->attachment__target_id ? 'Yes (ID: ' . $record->attachment__target_id . ')' : 'No';
          
          $output .= "<tr><td>{$record->id}</td><td>{$date}</td><td>{$desc}</td><td>{$attachment}</td></tr>";
        }
        
        $output .= '</table>';
      }
    }
    catch (\Exception $e) {
      $output .= '<p>Error checking database: ' . $e->getMessage() . '</p>';
    }
    
    return new Response($output);
  }
  
  /**
   * Handle the test file upload.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response with upload results.
   */
  public function handleTestFileUpload(Request $request) {
    // Enable error display for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    $output = '<h1>File Upload Test Results</h1>';
    
    $output .= '<h2>$_FILES Contents</h2>';
    $output .= '<pre>' . print_r($_FILES, TRUE) . '</pre>';
    
    $output .= '<h2>Request Files</h2>';
    $output .= '<pre>' . print_r($request->files->all(), TRUE) . '</pre>';
    
    // Attempt to process the file
    try {
      if ($request->files->has('test_file')) {
        $file = $request->files->get('test_file');
        
        $output .= '<h2>File Details</h2>';
        $output .= '<ul>';
        $output .= '<li>File name: ' . $file->getClientOriginalName() . '</li>';
        $output .= '<li>File size: ' . $file->getSize() . ' bytes</li>';
        $output .= '<li>File type: ' . $file->getClientMimeType() . '</li>';
        $output .= '<li>File error: ' . $file->getError() . '</li>';
        $output .= '<li>Is valid: ' . ($file->isValid() ? 'Yes' : 'No') . '</li>';
        $output .= '</ul>';
        
        if ($file->isValid()) {
          // Try to move the file
          $directory = 'public://tidy_feedback/attachments';
          
          // Create the directory if it doesn't exist
          $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
          
          // Log directory status
          $output .= '<h2>Directory Status</h2>';
          $output .= '<ul>';
          $output .= '<li>Directory: ' . $directory . '</li>';
          $output .= '<li>Real path: ' . $this->fileSystem->realpath($directory) . '</li>';
          $output .= '<li>Exists: ' . (file_exists($this->fileSystem->realpath($directory)) ? 'Yes' : 'No') . '</li>';
          $output .= '<li>Writable: ' . (is_writable($this->fileSystem->realpath($directory)) ? 'Yes' : 'No') . '</li>';
          $output .= '</ul>';
          
          // Save the file
          $destination = $directory . '/' . $file->getClientOriginalName();
          $file_uri = $this->fileSystem->saveUploadedFile(
            $file,
            $destination,
            FileSystemInterface::EXISTS_RENAME
          );
          
          if ($file_uri) {
            $output .= '<h2>File Saved Successfully</h2>';
            $output .= '<ul>';
            $output .= '<li>File URI: ' . $file_uri . '</li>';
            $output .= '<li>Real path: ' . $this->fileSystem->realpath($file_uri) . '</li>';
            $output .= '</ul>';
            
            // Try to create a file entity
            $file_entity = \Drupal\file\Entity\File::create([
              'uri' => $file_uri,
              'uid' => $this->currentUser()->id(),
              'status' => FILE_STATUS_PERMANENT,
              'filename' => $this->fileSystem->basename($file_uri),
            ]);
            
            $file_entity->save();
            
            $output .= '<p>File entity created with ID: ' . $file_entity->id() . '</p>';
          }
          else {
            $output .= '<h2>Error Saving File</h2>';
            $output .= '<p>Failed to save the uploaded file</p>';
          }
        }
        else {
          $output .= '<h2>Invalid File</h2>';
          $output .= '<p>File error code: ' . $file->getError() . '</p>';
          
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
          
          if (isset($errors[$file->getError()])) {
            $output .= '<p>Error: ' . $errors[$file->getError()] . '</p>';
          }
        }
      }
      else {
        $output .= '<h2>No File Uploaded</h2>';
        $output .= '<p>No file was found in the request</p>';
      }
    }
    catch (\Exception $e) {
      $output .= '<h2>Exception Caught</h2>';
      $output .= '<p>' . $e->getMessage() . '</p>';
      $output .= '<pre>' . $e->getTraceAsString() . '</pre>';
    }
    
    $output .= '<p><a href="/tidy-feedback/debug-file-uploads">Back to debug page</a></p>';
    
    return new Response($output);
  }
}