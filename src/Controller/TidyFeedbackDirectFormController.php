<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for direct feedback form with simplified file handling.
 */
class TidyFeedbackDirectFormController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Core\Datetime\TimeInterface
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
   * @param \Drupal\Core\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   */
  public function __construct(
    Connection $database,
    TimeInterface $time,
    UuidInterface $uuid
  ) {
    $this->database = $database;
    $this->time = $time;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('uuid')
    );
  }

  /**
   * Displays the direct feedback form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object with the feedback form.
   */
  public function displayForm(Request $request) {
    // Enable detailed error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);

    // Get parameters from URL
    $element_selector = $request->query->get('element_selector', '');
    $url = $request->query->get('url', '');
    
    // Log the request for debugging
    $this->getLogger('tidy_feedback')->notice('Direct form accessed with selector: @selector, url: @url', [
      '@selector' => $element_selector,
      '@url' => $url,
    ]);

    // Generate a CSRF token
    $csrf_token = \Drupal::csrfToken()->get('tidy_feedback_direct_form');

    // Build the form HTML
    $output = '<!DOCTYPE html>
<html>
<head>
    <title>Submit Feedback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0071b8;
            margin-top: 0;
        }
        .form-item {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        input[type="text"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-sizing: border-box;
        }
        textarea {
            min-height: 120px;
        }
        button, .button {
            background: #0071b8;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        button:hover, .button:hover {
            background: #005999;
        }
        .button--secondary {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ccc;
        }
        .button--secondary:hover {
            background: #e5e5e5;
        }
        .form-actions {
            margin-top: 20px;
        }
        .form-actions button,
        .form-actions .button {
            margin-right: 10px;
        }
        .messages {
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }
        .messages--error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .messages--status {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .description {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Submit Feedback</h1>';

    if (!empty($element_selector)) {
      $output .= '
        <div class="form-item">
            <strong>Selected Element:</strong> ' . htmlspecialchars($element_selector) . '
        </div>';
    }

    if (!empty($url)) {
      $output .= '
        <div class="form-item">
            <strong>Page:</strong> <a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a>
        </div>';
    }

    $output .= '
        <form action="/tidy-feedback/direct-form/submit" method="post" enctype="multipart/form-data" id="tidy-feedback-direct-form">
            <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
            <input type="hidden" name="element_selector" value="' . htmlspecialchars($element_selector) . '">
            <input type="hidden" name="url" value="' . htmlspecialchars($url) . '">
            <input type="hidden" name="browser_info" id="browser-info" value="{}">
            
            <div class="form-item">
                <label for="issue_type">Issue Type</label>
                <select id="issue_type" name="issue_type" required>
                    <option value="bug">Bug</option>
                    <option value="enhancement">Enhancement</option>
                    <option value="question">Question</option>
                    <option value="other" selected>Other</option>
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
                <div class="description">Please describe the issue or suggestion in detail.</div>
            </div>
            
            <div class="form-item">
                <label for="attachment">Attachment</label>
                <input type="file" id="attachment" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">
                <div class="description">Upload a file to provide additional context (optional). Allowed extensions: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, txt, csv. Maximum size: 5MB.</div>
            </div>
            
            <div class="form-actions">';
    
    if (!empty($url)) {
      $output .= '
                <button type="submit">Submit Feedback</button>
                <a href="' . htmlspecialchars($url) . '" class="button button--secondary">Cancel</a>';
    } else {
      $output .= '
                <button type="submit">Submit Feedback</button>
                <a href="/" class="button button--secondary">Cancel</a>';
    }
    
    $output .= '
            </div>
        </form>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            try {
                // Collect browser info
                var browserInfo = {
                    userAgent: navigator.userAgent,
                    platform: navigator.platform,
                    vendor: navigator.vendor,
                    language: navigator.language,
                    screenWidth: window.screen.width,
                    screenHeight: window.screen.height
                };
                document.getElementById("browser-info").value = JSON.stringify(browserInfo);
            } catch (error) {
                console.error("Error setting browser info:", error);
            }
        });
    </script>
</body>
</html>';

    return new Response($output);
  }

  /**
   * Handles the form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   */
  public function handleFormSubmission(Request $request) {
    // Enable detailed error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    try {
      // Extract form data
      $csrf_token = $request->request->get('csrf_token');
      $element_selector = $request->request->get('element_selector', '');
      $url = $request->request->get('url', '');
      $issue_type = $request->request->get('issue_type', 'other');
      $severity = $request->request->get('severity', 'normal');
      $description = $request->request->get('description', '');
      $browser_info = $request->request->get('browser_info', '{}');
      
      // Log form data for debugging
      $this->getLogger('tidy_feedback')->notice('Form data received: @data', [
        '@data' => json_encode([
          'element_selector' => $element_selector,
          'url' => $url,
          'issue_type' => $issue_type,
          'severity' => $severity,
          'description_length' => strlen($description),
          'browser_info' => $browser_info,
        ])
      ]);
      
      // Validate CSRF token
      if (!\Drupal::csrfToken()->validate($csrf_token, 'tidy_feedback_direct_form')) {
        throw new \Exception('Invalid security token');
      }
      
      // Validate required fields
      if (empty($description)) {
        throw new \Exception('Description is required');
      }
      
      // Process file upload using direct PHP method
      $attachment_fid = NULL;
      if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        // Log file information
        $this->getLogger('tidy_feedback')->notice('File uploaded: @name, @size bytes, @type', [
          '@name' => $_FILES['attachment']['name'],
          '@size' => $_FILES['attachment']['size'],
          '@type' => $_FILES['attachment']['type'],
        ]);
        
        // Create the destination directory
        $directory = 'public://tidy_feedback/attachments';
        $directory_path = \Drupal::service('file_system')->realpath($directory);
        
        if (!file_exists($directory_path)) {
          mkdir($directory_path, 0755, TRUE);
        }
        
        // Generate a unique filename
        $filename = time() . '_' . $_FILES['attachment']['name'];
        $destination = $directory . '/' . $filename;
        $destination_path = \Drupal::service('file_system')->realpath($directory) . '/' . $filename;
        
        // Move the uploaded file
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destination_path)) {
          // Create a managed file entity
          $file = File::create([
            'uri' => $destination,
            'uid' => $this->currentUser()->id(),
            'status' => FILE_STATUS_PERMANENT,
            'filename' => $filename,
          ]);
          $file->save();
          
          $attachment_fid = $file->id();
          
          $this->getLogger('tidy_feedback')->notice('File saved as permanent with ID: @id', [
            '@id' => $attachment_fid,
          ]);
        } else {
          $this->getLogger('tidy_feedback')->error('Failed to move uploaded file: @source to @dest', [
            '@source' => $_FILES['attachment']['tmp_name'],
            '@dest' => $destination_path,
          ]);
        }
      }
      
      // Insert feedback into database
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
      
      // Add file usage if attachment was provided
      if ($attachment_fid) {
        \Drupal::service('file.usage')->add(
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
      
      // Show success message and redirect
      $output = '<!DOCTYPE html>
<html>
<head>
    <title>Feedback Submitted</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0071b8;
            margin-top: 0;
        }
        .messages {
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }
        .messages--status {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .button {
            background: #0071b8;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }
        .button:hover {
            background: #005999;
        }
    </style>';
      
      // Add redirect if URL is provided
      if (!empty($url)) {
        $output .= '
    <meta http-equiv="refresh" content="3;url=' . htmlspecialchars($url) . '">';
      }
      
      $output .= '
</head>
<body>
    <div class="container">
        <h1>Feedback Submitted</h1>
        <div class="messages messages--status">
            <p>Thank you for your feedback. It has been submitted successfully (ID: ' . $id . ').</p>';
      
      if (!empty($url)) {
        $output .= '
            <p>You will be redirected back to the page in a few seconds.</p>';
      }
      
      $output .= '
        </div>';
      
      if (!empty($url)) {
        $output .= '
        <a href="' . htmlspecialchars($url) . '" class="button">Return Now</a>';
      } else {
        $output .= '
        <a href="/" class="button">Return to Home</a>';
      }
      
      $output .= '
    </div>
</body>
</html>';
      
      return new Response($output);
      
    } catch (\Exception $e) {
      // Log error
      $this->getLogger('tidy_feedback')->error('Error processing feedback: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      // Show error page
      $output = '<!DOCTYPE html>
<html>
<head>
    <title>Error</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #e53935;
            margin-top: 0;
        }
        .messages {
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }
        .messages--error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .button {
            background: #0071b8;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }
        .button:hover {
            background: #005999;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Error</h1>
        <div class="messages messages--error">
            <p>There was an error processing your feedback:</p>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
        </div>
        <a href="javascript:history.back()" class="button">Go Back</a>
    </div>
</body>
</html>';
      
      return new Response($output, 400);
    }
  }
}