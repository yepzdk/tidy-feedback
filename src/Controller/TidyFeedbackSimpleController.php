<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Simple controller for direct testing of form submission.
 */
class TidyFeedbackSimpleController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new simple controller.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    Connection $database,
    UuidInterface $uuid,
    TimeInterface $time
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('uuid'),
      $container->get('datetime.time')
    );
  }
  
  /**
   * Gets JavaScript for the form.
   *
   * @return array
   *   A render array for the JavaScript.
   */
  private function getFormJavaScript() {
    return [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => "
        document.addEventListener('DOMContentLoaded', function() {
          // Set browser info on page load
          var browserInfo = {
            userAgent: navigator.userAgent,
            screenWidth: window.screen.width, 
            screenHeight: window.screen.height,
            viewportWidth: window.innerWidth,
            viewportHeight: window.innerHeight,
            devicePixelRatio: window.devicePixelRatio || 1,
            platform: navigator.platform,
            language: navigator.language
          };
          document.getElementById('tidy-feedback-browser-info').value = JSON.stringify(browserInfo);
          
          // Handle cancel button
          var cancelButton = document.getElementById('tidy-feedback-cancel');
          if (cancelButton) {
            cancelButton.addEventListener('click', function() {
              if (window.parent.Drupal && window.parent.Drupal.dialog) {
                window.parent.jQuery('.ui-dialog-titlebar-close').click();
              } else {
                window.location.href = '/admin/reports/tidy-feedback';
              }
            });
          }
        });
      ",
    ];
  }

  /**
   * Displays a simple test form.
   *
   * @param string $element_selector
   *   Optional CSS selector of the element being reported.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   A simple form or response object.
   */
  public function testForm($element_selector = '') {
    // Get current URL
    $current_url = \Drupal::request()->getUri();
  
    $output = '
    <div class="tidy-feedback-simple-test">
      <h2>Submit Feedback</h2>
      <form method="post" action="/tidy-feedback/simple-submit" enctype="multipart/form-data">
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
          <div class="description">Upload a file to provide additional context (optional). Allowed extensions: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, txt, csv. Maximum size: 5MB.</div>
        </div>
      
        <!-- Hidden fields for additional data -->
        <input type="hidden" id="tidy-feedback-url" name="url" value="' . htmlspecialchars($current_url) . '">
        <input type="hidden" id="tidy-feedback-element-selector" name="element_selector" value="' . htmlspecialchars($element_selector) . '">
        <input type="hidden" id="tidy-feedback-browser-info" name="browser_info" value="{}">
      
        <div class="form-actions">
          <button type="submit" class="button button--primary">Submit Feedback</button>
          <button type="button" id="tidy-feedback-cancel" class="button">Cancel</button>
        </div>
      </form>
    
      <div id="tidy-feedback-result"></div>
    </div>';
  
    return new Response($output);
  }
  
  /**
   * Handles direct form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
   *   A response object.
   */
  public function handleSubmit(Request $request) {
    try {
      // Get form data and files
      $data = $request->request->all();
      $files = $request->files->all();
    
      // Log what we received
      $this->getLogger('tidy_feedback')->notice("Feedback form data: @data", [
        "@data" => print_r($data, TRUE),
      ]);
      $this->getLogger('tidy_feedback')->notice("Feedback form files: @files", [
        "@files" => print_r($files, TRUE),
      ]);
    
      // Basic validation
      if (empty($data['description'])) {
        throw new \Exception('Description is required');
      }
    
      // Process browser info - it might be a JSON string that needs decoding
      $browserInfo = isset($data["browser_info"]) ? $data["browser_info"] : "{}";
      if (is_string($browserInfo) && !empty($browserInfo)) {
        // Check if it's already a JSON string
        if (substr($browserInfo, 0, 1) === "{" && json_decode($browserInfo) !== NULL) {
          // It's already valid JSON, keep as is
        } else {
          // Convert to JSON if it's not already
          $browserInfo = json_encode(["raw_data" => $browserInfo]);
        }
      } else {
        // If empty or not a string, create an empty JSON object
        $browserInfo = "{}";
      }
    
      // Get values with defaults
      $url = isset($data["url"]) ? $data["url"] : $request->getUri();
      $elementSelector = isset($data["element_selector"]) ? $data["element_selector"] : "";
    
      // Process file attachment if present
      $attachment_fid = NULL;
      if (!empty($files['files']['attachment'])) {
        $uploaded_file = $files['files']['attachment'];
      
        if ($uploaded_file->isValid()) {
          // Prepare directory
          $directory = 'public://tidy_feedback/attachments';
          if (!\Drupal::service('file_system')->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS)) {
            throw new \Exception("Failed to create directory: $directory");
          }
        
          // Save the file
          $filename = $uploaded_file->getClientOriginalName();
          $destination = $directory . '/' . $filename;
        
          $file_uri = \Drupal::service('file_system')->saveUploadedFile(
            $uploaded_file, 
            $destination,
            \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME
          );
        
          if (!$file_uri) {
            throw new \Exception("Failed to save uploaded file");
          }
        
          // Create file entity
          $file = \Drupal\file\Entity\File::create([
            'uri' => $file_uri,
            'uid' => $this->currentUser()->id(),
            'status' => FILE_STATUS_PERMANENT,
            'filename' => \Drupal::service('file_system')->basename($file_uri),
          ]);
          $file->save();
        
          $attachment_fid = $file->id();
        
          $this->getLogger('tidy_feedback')->notice(
            "Feedback form: File uploaded successfully: @filename (FID: @fid)",
            ["@filename" => $file->getFilename(), "@fid" => $attachment_fid]
          );
        } else {
          $this->getLogger('tidy_feedback')->error(
            "File upload error: @error",
            ["@error" => $uploaded_file->getError()]
          );
        }
      }
    
      // Insert into database
      $id = $this->database->insert('tidy_feedback')
        ->fields([
          'uuid' => $this->uuid->generate(),
          'uid' => $this->currentUser()->id(),
          'created' => $this->time->getRequestTime(),
          'changed' => $this->time->getRequestTime(),
          'issue_type' => $data['issue_type'] ?? 'other',
          'severity' => $data['severity'] ?? 'normal',
          'description__value' => $data['description'],
          'description__format' => 'basic_html',
          'url' => $url,
          'element_selector' => $elementSelector,
          'browser_info' => $browserInfo,
          'status' => 'new',
          'attachment__target_id' => $attachment_fid,
        ])
        ->execute();
    
      // Add file usage record
      if ($attachment_fid) {
        \Drupal::service('file.usage')->add(
          \Drupal\file\Entity\File::load($attachment_fid),
          'tidy_feedback',
          'tidy_feedback',
          $id
        );
      }
    
      $this->getLogger('tidy_feedback')->notice(
        "Feedback form: Feedback #@id submitted successfully.",
        ["@id" => $id]
      );
    
      // For AJAX requests, return JSON
      if ($request->isXmlHttpRequest()) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'status' => 'success',
          'message' => 'Feedback submitted successfully',
          'id' => $id,
        ]);
      }
    
      // For regular form posts, redirect to the report list
      return new RedirectResponse('/admin/reports/tidy-feedback');
    
    } catch (\Exception $e) {
      $this->getLogger('tidy_feedback')->error(
        "Feedback form error: @error\nTrace: @trace",
        [
          "@error" => $e->getMessage(),
          "@trace" => $e->getTraceAsString(),
        ]
      );
    
      // For AJAX requests, return JSON error
      if ($request->isXmlHttpRequest()) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
          'status' => 'error',
          'message' => $e->getMessage(),
        ], 500);
      }
    
      // Display error information for regular requests
      $output = '
      <div class="messages messages--error">
        <h2>Error submitting feedback</h2>
        <p>' . $e->getMessage() . '</p>
        <pre>' . $e->getTraceAsString() . '</pre>
        <p><a href="/tidy-feedback/simple-test">Try again</a> | 
        <a href="/admin/reports/tidy-feedback">Feedback Reports</a></p>
      </div>';
    
      return new Response($output, 500);
    }
  }
}