<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;

/**
 * Controller for the direct HTML form approach.
 */
class TidyFeedbackDirectController extends ControllerBase {

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
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * Constructs a new controller.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   */
  public function __construct(
    Connection $database,
    FileSystemInterface $file_system,
    FileUsageInterface $file_usage,
    TimeInterface $time,
    UuidInterface $uuid
  ) {
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->fileUsage = $file_usage;
    $this->time = $time;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_system'),
      $container->get('file.usage'),
      $container->get('datetime.time'),
      $container->get('uuid')
    );
  }

  /**
   * Handles both form display and form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   */
  public function handleForm(Request $request) {
    // Handle POST requests (form submissions)
    if ($request->isMethod('POST')) {
      return $this->handleFormSubmission($request);
    }
    
    // Handle GET requests (form display)
    $element_selector = $request->query->get('element_selector', '');
    $url = $request->query->get('url', '');
    
    // Create a styled form
    $output = '<!DOCTYPE html><html lang="en"><head><title>Submit Feedback</title>';
    $output .= '<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      :root {
        --primary-color: #0071b8;
        --primary-hover: #00539f;
        --light-bg: #f7f9fc;
        --border-color: #ddd;
        --success-color: #43a047;
      }
      body {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        line-height: 1.6;
        max-width: 800px;
        margin: 0 auto;
        padding: 2rem;
        color: #333;
        background-color: #fff;
      }
      .tidy-feedback-page {
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        background-color: #fff;
      }
      .tidy-feedback-page__header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
      }
      h1 {
        margin-top: 0;
        color: var(--primary-color);
        font-weight: 600;
      }
      .element-info {
        background-color: var(--light-bg);
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 4px;
        border-left: 4px solid var(--primary-color);
        word-break: break-word;
      }
      .form-item {
        margin-bottom: 1.5rem;
      }
      label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.5rem;
      }
      select, textarea {
        width: 100%;
        padding: 0.75rem;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        font-size: 1rem;
      }
      textarea {
        min-height: 150px;
        resize: vertical;
      }
      .description {
        font-size: 0.875rem;
        color: #666;
        margin-top: 0.5rem;
      }
      button, .button {
        display: inline-block;
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-weight: 500;
        transition: background-color 0.2s;
      }
      button:hover, .button:hover {
        background-color: var(--primary-hover);
      }
      .button--secondary {
        background-color: transparent;
        color: var(--primary-color);
        border: 1px solid var(--primary-color);
        margin-left: 0.5rem;
      }
      .button--secondary:hover {
        background-color: var(--light-bg);
      }
      .form-actions {
        margin-top: 2rem;
        display: flex;
        gap: 1rem;
      }
      @media (max-width: 576px) {
        body {
          padding: 1rem;
        }
        .tidy-feedback-page {
          padding: 1rem;
          box-shadow: none;
        }
        .form-actions {
          flex-direction: column;
        }
        .button--secondary {
          margin-left: 0;
          margin-top: 0.5rem;
        }
      }
    </style>';
    
    // Add javascript to set browser info
    $output .= '<script>
      document.addEventListener("DOMContentLoaded", function() {
        try {
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
          document.getElementById("browser-info").value = JSON.stringify(browserInfo);
        } catch (error) {
          console.error("Error setting browser info:", error);
        }
      });
    </script></head><body>
    <div class="tidy-feedback-page">
      <div class="tidy-feedback-page__header">
        <h1>Submit Feedback</h1>';
    
    if (!empty($element_selector)) {
      $output .= '<div class="element-info">';
      $output .= '<strong>Selected element:</strong> ' . htmlspecialchars($element_selector);
      $output .= '</div>';
    }
    
    if (!empty($url)) {
      $output .= '<p><strong>URL:</strong> <a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a></p>';
    }
    
    $output .= '</div>'; // Close header
    
    // Use the current URL as the form action to handle submission in the same controller
    $current_url = $request->getUri();
    
    $output .= '<form method="post" action="' . $current_url . '" enctype="multipart/form-data">';
    
    $output .= '<div class="form-item">';
    $output .= '<label for="issue_type">Issue Type</label>';
    $output .= '<select id="issue_type" name="issue_type">';
    $output .= '<option value="bug">Bug</option>';
    $output .= '<option value="enhancement">Enhancement</option>';
    $output .= '<option value="question">Question</option>';
    $output .= '<option value="other" selected>Other</option>';
    $output .= '</select>';
    $output .= '</div>';
    
    $output .= '<div class="form-item">';
    $output .= '<label for="severity">Severity</label>';
    $output .= '<select id="severity" name="severity">';
    $output .= '<option value="critical">Critical</option>';
    $output .= '<option value="high">High</option>';
    $output .= '<option value="normal" selected>Normal</option>';
    $output .= '<option value="low">Low</option>';
    $output .= '</select>';
    $output .= '</div>';
    
    $output .= '<div class="form-item">';
    $output .= '<label for="description">Description</label>';
    $output .= '<textarea id="description" name="description" required></textarea>';
    $output .= '<div class="description">Please describe the issue or suggestion in detail.</div>';
    $output .= '</div>';
    
    $output .= '<div class="form-item">';
    $output .= '<label for="attachment">Attachment</label>';
    $output .= '<input type="file" id="attachment" name="files[attachment]" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">';
    $output .= '<div class="description">Upload a file to provide additional context (optional).</div>';
    $output .= '</div>';
    
    $output .= '<input type="hidden" name="element_selector" value="' . htmlspecialchars($element_selector) . '">';
    $output .= '<input type="hidden" name="url" value="' . htmlspecialchars($url) . '">';
    $output .= '<input type="hidden" id="browser-info" name="browser_info" value="{}">';
    
    $output .= '<div class="form-actions">';
    $output .= '<button type="submit" class="button">Submit Feedback</button>';
    
    if (!empty($url)) {
      $output .= '<a href="' . htmlspecialchars($url) . '" class="button button--secondary">Cancel</a>';
    } else {
      $output .= '<a href="/" class="button button--secondary">Cancel</a>';
    }
    
    $output .= '</div>'; // Close form-actions
    $output .= '</form>';
    $output .= '</div>'; // Close tidy-feedback-page
    $output .= '</body></html>';
    
    return new Response($output);
  }

  /**
   * Handles form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   */
  protected function handleFormSubmission(Request $request) {
    // Enable maximum error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    try {
      // Extract form data and files
      $data = $request->request->all();
      $files = $request->files->all();
      
      // Log the request data for debugging
      $this->getLogger('tidy_feedback')->notice('Form submission data: @data', [
        '@data' => print_r($data, TRUE)
      ]);
      $this->getLogger('tidy_feedback')->notice('Files in request: @files', [
        '@files' => print_r($files, TRUE)
      ]);
      
      // Basic validation
      if (empty($data['description'])) {
        throw new \Exception('Description is required');
      }
      
      // Get form values
      $url = isset($data['url']) ? $data['url'] : '';
      $element_selector = isset($data['element_selector']) ? $data['element_selector'] : '';
      $issue_type = isset($data['issue_type']) ? $data['issue_type'] : 'other';
      $severity = isset($data['severity']) ? $data['severity'] : 'normal';
      $description = $data['description'];
      $browser_info = isset($data['browser_info']) ? $data['browser_info'] : '{}';
      
      // Process file attachment if present
      $attachment_fid = NULL;
      if (!empty($files['files']['attachment'])) {
        $uploaded_file = $files['files']['attachment'];
        
        try {
            if ($uploaded_file->isValid()) {
              // Log upload info for debugging
              $this->getLogger('tidy_feedback')->notice('File upload received: @name, @size bytes, @type', [
                '@name' => $uploaded_file->getClientOriginalName(),
                '@size' => $uploaded_file->getSize(),
                '@type' => $uploaded_file->getClientMimeType(),
              ]);
            
              // Prepare directory - with more robust error handling
              $directory = 'public://tidy_feedback/attachments';
            
              // Check directory existence and create with more permissive permissions
              if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
                $real_path = $this->fileSystem->realpath($directory) ?: 'directory not found';
                $this->getLogger('tidy_feedback')->error('Failed to prepare directory: @dir (real path: @real)', [
                  '@dir' => $directory, 
                  '@real' => $real_path
                ]);
              
                // Attempt more forceful directory creation
                $parent_dir = $this->fileSystem->dirname($directory);
                $this->fileSystem->prepareDirectory($parent_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
              
                // Try creating directly with PHP
                $real_parent = $this->fileSystem->realpath($parent_dir);
                if ($real_parent && is_dir($real_parent)) {
                  $dir_name = $this->fileSystem->basename($directory);
                  $full_path = $real_parent . '/' . $dir_name;
                  @mkdir($full_path, 0777, TRUE);
                  chmod($full_path, 0777);
                }
              
                // Check again after attempts
                if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
                  throw new \Exception("Failed to create directory: $directory");
                }
              }
            
              // Save file - with better error handling
              $filename = $uploaded_file->getClientOriginalName();
              $destination = $directory . '/' . $filename;
            
              $this->getLogger('tidy_feedback')->notice('Attempting to save file to: @destination', [
                '@destination' => $destination
              ]);
            
              try {
                $file_uri = $this->fileSystem->saveUploadedFile(
                  $uploaded_file, 
                  $destination,
                  FileSystemInterface::EXISTS_RENAME
                );
              }
              catch (\Exception $e) {
                $this->getLogger('tidy_feedback')->error('Exception saving file: @error', [
                  '@error' => $e->getMessage()
                ]);
              
                // Try alternative approach if normal approach fails
                $temp_path = $uploaded_file->getRealPath();
                if ($temp_path && file_exists($temp_path)) {
                  $dest_path = $this->fileSystem->realpath($directory) . '/' . $filename;
                  $success = @copy($temp_path, $dest_path);
                  if ($success) {
                    $file_uri = $directory . '/' . $filename;
                  }
                }
              }
            
              if (!$file_uri) {
                throw new \Exception("Failed to save uploaded file");
              }
            
              // Create file entity - with better error handling
              try {
                $file = File::create([
                  'uri' => $file_uri,
                  'uid' => $this->currentUser()->id(),
                  'status' => FILE_STATUS_PERMANENT,
                  'filename' => $this->fileSystem->basename($file_uri),
                ]);
                $file->save();
              
                $attachment_fid = $file->id();
                $this->getLogger('tidy_feedback')->notice('File entity created with ID: @id', [
                  '@id' => $attachment_fid
                ]);
              }
              catch (\Exception $e) {
                $this->getLogger('tidy_feedback')->error('Failed to create file entity: @error', [
                  '@error' => $e->getMessage()
                ]);
                throw $e;
              }
            } else {
              // Log file upload error
              $error_code = $uploaded_file->getError();
              $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'The file exceeds the maximum upload size in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'The file exceeds the maximum upload size specified in the HTML form',
                UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder for file uploads',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write the file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
              ];
              $error_message = isset($error_messages[$error_code]) ? 
                $error_messages[$error_code] : 'Unknown error code: ' . $error_code;
            
              $this->getLogger('tidy_feedback')->error('File upload validation failed: @error', [
                '@error' => $error_message
              ]);
            }
        } catch (\Exception $e) {
          $this->getLogger('tidy_feedback')->error('Error processing attachment: @error, Trace: @trace', [
            '@error' => $e->getMessage(),
            '@trace' => $e->getTraceAsString()
          ]);
          
          // Try to diagnose file upload issues
          $upload_max_filesize = ini_get('upload_max_filesize');
          $post_max_size = ini_get('post_max_size');
          $tmp_dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
          
          $this->getLogger('tidy_feedback')->notice('PHP File upload settings: max_filesize=@size, post_max_size=@post, tmp_dir=@tmp', [
            '@size' => $upload_max_filesize,
            '@post' => $post_max_size,
            '@tmp' => $tmp_dir
          ]);
          
          // Check for common upload issues
          if (!is_writable($tmp_dir)) {
            $this->getLogger('tidy_feedback')->error('Temporary upload directory is not writable: @dir', [
              '@dir' => $tmp_dir
            ]);
          }
          
          // Continue without the file
        }
      }
      
      // Insert into database
      $id = $this->database->insert('tidy_feedback')
        ->fields([
          'uuid' => $this->uuid->generate(),
          'uid' => $this->currentUser()->id(),
          'created' => $this->time->getRequestTime(),
          'changed' => $this->time->getRequestTime(),
          'issue_type' => $issue_type,
          'severity' => $severity,
          'description__value' => $description,
          'description__format' => 'basic_html',
          'url' => $url,
          'element_selector' => $element_selector,
          'browser_info' => $browser_info,
          'status' => 'new',
          'attachment__target_id' => $attachment_fid,
        ])
        ->execute();
      
      // Register file usage if attachment was uploaded
      if ($attachment_fid) {
        $this->fileUsage->add(
          File::load($attachment_fid),
          'tidy_feedback',
          'tidy_feedback',
          $id
        );
      }
      
      // Log success
      $this->getLogger('tidy_feedback')->notice('Feedback submitted with ID: @id', [
        '@id' => $id,
      ]);
      
      // Redirect back to the originating URL or front page
      if (!empty($url)) {
        return new RedirectResponse($url);
      } else {
        return new RedirectResponse('/');
      }
      
    } catch (\Exception $e) {
      // Log detailed error information
      $this->getLogger('tidy_feedback')->error('Error processing feedback: @error, Trace: @trace', [
        '@error' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
      ]);
      
      // Log other diagnostic information
      $this->getLogger('tidy_feedback')->notice('Server info: PHP @version, memory_limit=@memory', [
        '@version' => phpversion(),
        '@memory' => ini_get('memory_limit')
      ]);
      
      // Show error page
      return new Response('<!DOCTYPE html><html lang="en"><head><title>Error</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
          body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            color: #333;
          }
          .error-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            text-align: center;
          }
          h1 {
            color: #d32f2f;
            margin-top: 0;
          }
          .error-message {
            background-color: #ffebee;
            border-left: 4px solid #d32f2f;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            text-align: left;
          }
          .button {
            display: inline-block;
            background-color: #0071b8;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 1.5rem;
          }
          .button:hover {
            background-color: #00539f;
          }
        </style>
      </head><body>
        <div class="error-container">
          <h1>Error</h1>
          <div class="error-message">
            <p>An error occurred while processing your feedback.</p>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
          </div>
          <a href="javascript:history.back()" class="button">Go back and try again</a>
        </div>
      </body></html>', 500);
    }
  }

}