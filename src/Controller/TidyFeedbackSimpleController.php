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
   * Displays a simple test form.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   A simple form or response object.
   */
  public function testForm() {
    $output = '
    <div class="tidy-feedback-simple-test">
      <h2>Simple Feedback Test Form</h2>
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
        </div>
        
        <div class="form-actions">
          <button type="submit" class="button button--primary">Submit Form</button>
        </div>
      </form>
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
      $this->getLogger('tidy_feedback')->notice("Simple form data: @data", [
        "@data" => print_r($data, TRUE),
      ]);
      $this->getLogger('tidy_feedback')->notice("Simple form files: @files", [
        "@files" => print_r($files, TRUE),
      ]);
      
      // Basic validation
      if (empty($data['description'])) {
        throw new \Exception('Description is required');
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
          'url' => '(simple test form)',
          'element_selector' => '',
          'browser_info' => '{}',
          'status' => 'new',
        ])
        ->execute();
      
      $this->getLogger('tidy_feedback')->notice(
        "Simple form: Feedback #@id submitted successfully.",
        ["@id" => $id]
      );
      
      // Redirect to the report list
      return new RedirectResponse('/admin/reports/tidy-feedback');
      
    } catch (\Exception $e) {
      $this->getLogger('tidy_feedback')->error(
        "Simple form error: @error\nTrace: @trace",
        [
          "@error" => $e->getMessage(),
          "@trace" => $e->getTraceAsString(),
        ]
      );
      
      // Display error information
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