<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for direct testing.
 */
class TidyFeedbackDirectTestController extends ControllerBase {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new controller.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')
    );
  }

  /**
   * Displays a very simple test form.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A simple form for testing.
   */
  public function directTestForm() {
    // Log that this method is being accessed
    $this->getLogger('tidy_feedback')->notice("Direct test form controller accessed");
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <title>Direct Test Form</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-item { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        button { padding: 8px 15px; background: #0071b8; color: white; border: none; cursor: pointer; }
        .messages { padding: 10px; margin-top: 20px; border: 1px solid #ddd; }
        .messages--status { background: #f8fff0; border-color: #be7; }
        .messages--error { background: #fff0f0; border-color: #e7b; }
    </style>
</head>
<body>
    <h1>Direct Test Form</h1>
    <p>This is a simplified form for direct testing.</p>
    
    <form method="post" action="/tidy-feedback/direct-test-submit" enctype="multipart/form-data">
        <div class="form-item">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5" required></textarea>
        </div>
        
        <div class="form-item">
            <label for="attachment">Attachment</label>
            <input type="file" id="attachment" name="files[attachment]">
            <div class="description">Upload a file (optional)</div>
        </div>
        
        <div class="form-actions">
            <button type="submit">Submit Test Form</button>
        </div>
    </form>
    
    <div id="result"></div>
    
    <p><a href="/admin/reports/tidy-feedback">View all feedback</a></p>
</body>
</html>';
    
    $response = new Response($html);
    $response->headers->set('Content-Type', 'text/html');
    
    return $response;
  }
  
  /**
   * Handles the form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   */
  public function handleDirectTestSubmit(Request $request) {
    // Log that this method is being accessed
    $this->getLogger('tidy_feedback')->notice("Direct test form submission handler accessed");
    
    try {
      // Log received data
      $this->getLogger('tidy_feedback')->notice(
        "Direct test data: @data",
        ["@data" => print_r($request->request->all(), TRUE)]
      );
      
      $this->getLogger('tidy_feedback')->notice(
        "Direct test files: @files",
        ["@files" => print_r($request->files->all(), TRUE)]
      );
      
      // Basic validation
      $description = $request->request->get('description');
      if (empty($description)) {
        throw new \Exception('Description is required');
      }
      
      // Insert into database
      $id = \Drupal::database()->insert('tidy_feedback')
        ->fields([
          'uuid' => \Drupal::service('uuid')->generate(),
          'uid' => $this->currentUser()->id(),
          'created' => \Drupal::time()->getRequestTime(),
          'changed' => \Drupal::time()->getRequestTime(),
          'issue_type' => 'other',
          'severity' => 'normal',
          'description__value' => $description,
          'description__format' => 'basic_html',
          'url' => \Drupal::request()->getUri(),
          'element_selector' => 'direct-test',
          'browser_info' => '{}',
          'status' => 'new',
        ])
        ->execute();
      
      // Log success
      $this->getLogger('tidy_feedback')->notice(
        "Direct test submission successful! ID: @id",
        ["@id" => $id]
      );
      
      // Render success page
      $html = '<!DOCTYPE html>
<html>
<head>
    <title>Form Submitted</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { background: #f8fff0; border: 1px solid #be7; padding: 15px; margin-bottom: 20px; }
    </style>
    <meta http-equiv="refresh" content="3;url=/admin/reports/tidy-feedback">
</head>
<body>
    <h1>Form Submitted Successfully</h1>
    <div class="success">
        <p>Your feedback has been submitted successfully (ID: ' . $id . ').</p>
        <p>You will be redirected to the feedback list in 3 seconds.</p>
    </div>
    <p><a href="/admin/reports/tidy-feedback">View all feedback</a></p>
    <p><a href="/tidy-feedback/direct-test">Submit another test</a></p>
</body>
</html>';
      
      $response = new Response($html);
      $response->headers->set('Content-Type', 'text/html');
      
      return $response;
      
    } catch (\Exception $e) {
      // Log error
      $this->getLogger('tidy_feedback')->error(
        "Direct test error: @error",
        ["@error" => $e->getMessage()]
      );
      
      // Render error page
      $html = '<!DOCTYPE html>
<html>
<head>
    <title>Form Submission Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .error { background: #fff0f0; border: 1px solid #e7b; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Form Submission Error</h1>
    <div class="error">
        <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
    </div>
    <p><a href="/tidy-feedback/direct-test">Try again</a></p>
    <p><a href="/admin/reports/tidy-feedback">View all feedback</a></p>
</body>
</html>';
      
      $response = new Response($html, 500);
      $response->headers->set('Content-Type', 'text/html');
      
      return $response;
    }
  }

}