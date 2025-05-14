<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;

/**
 * Controller for the Tidy Feedback form page.
 */
class TidyFeedbackPageController extends ControllerBase {

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * Constructs a new TidyFeedbackPageController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   */
  public function __construct(
    Connection $database,
    FileSystemInterface $file_system,
    FileUsageInterface $file_usage,
    EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $time,
    UuidInterface $uuid
  ) {
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->fileUsage = $file_usage;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('uuid')
    );
  }

  /**
   * Displays the feedback form page.
   *
   * @return array
   *   A render array for the feedback form page.
   */
  public function displayForm() {
    // Get query parameters
    $request = $this->getRequest();
    $element_selector = $request->query->get('element_selector', '');
    $url = $request->query->get('url', '');
    
    // Default to current URL if none provided
    if (empty($url)) {
      $url = $request->getUri();
    }
    
    // Basic content render array
    $content = [
      '#markup' => '<div class="tidy-feedback-page">
        <div class="tidy-feedback-page__header">
          <h1>' . $this->t('Submit Feedback') . '</h1>
          ' . (!empty($element_selector) ? '<div class="tidy-feedback-page__element-info">
            <strong>' . $this->t('Selected element:') . '</strong> ' . htmlspecialchars($element_selector) . '
          </div>' : '') . '
        </div>
        <div class="tidy-feedback-page__content">
          <form id="tidy-feedback-form" class="tidy-feedback-form" action="' . Url::fromRoute('tidy_feedback.page_submit')->toString() . '" method="post" enctype="multipart/form-data">
            <div class="form-item">
              <label for="issue_type">' . $this->t('Issue Type') . '</label>
              <select id="issue_type" name="issue_type" required>
                <option value="bug">' . $this->t('Bug') . '</option>
                <option value="enhancement">' . $this->t('Enhancement') . '</option>
                <option value="question">' . $this->t('Question') . '</option>
                <option value="other">' . $this->t('Other') . '</option>
              </select>
            </div>
            <div class="form-item">
              <label for="severity">' . $this->t('Severity') . '</label>
              <select id="severity" name="severity" required>
                <option value="critical">' . $this->t('Critical') . '</option>
                <option value="high">' . $this->t('High') . '</option>
                <option value="normal" selected>' . $this->t('Normal') . '</option>
                <option value="low">' . $this->t('Low') . '</option>
              </select>
            </div>
            <div class="form-item">
              <label for="description">' . $this->t('Description') . '</label>
              <textarea id="description" name="description" rows="5" required></textarea>
              <div class="description">' . $this->t('Please describe the issue or suggestion in detail.') . '</div>
            </div>
            <div class="form-item">
              <label for="attachment">' . $this->t('Attachment') . '</label>
              <input type="file" id="attachment" name="files[attachment]" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">
              <div class="description">' . $this->t('Upload a file to provide additional context (optional).') . '</div>
            </div>
            <input type="hidden" id="tidy-feedback-url" name="url" value="' . htmlspecialchars($url) . '">
            <input type="hidden" id="tidy-feedback-element-selector" name="element_selector" value="' . htmlspecialchars($element_selector) . '">
            <input type="hidden" id="tidy-feedback-browser-info" name="browser_info" value="{}">
            <div class="form-actions">
              <button type="submit" id="tidy-feedback-submit" class="button button--primary">' . $this->t('Submit Feedback') . '</button>
              <a href="' . htmlspecialchars($url) . '" id="tidy-feedback-cancel" class="button">' . $this->t('Cancel') . '</a>
            </div>
          </form>
        </div>
      </div>',
      '#allowed_tags' => ['div', 'h1', 'form', 'input', 'textarea', 'label', 'select', 'option', 'button', 'a', 'strong'],
    ];
    
    return [
      '#type' => 'page',
      'content' => $content,
      '#attached' => [
        'library' => ['tidy_feedback/tidy_feedback_form_page'],
      ],
    ];
  }

  /**
   * Handles the form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response after processing the form.
   */
  public function handleSubmit(Request $request) {
    try {
      // Get form data and files
      $data = $request->request->all();
      $files = $request->files->all();
      
      // Log what we received for debugging
      $this->getLogger('tidy_feedback')->notice("Form data received: @data", [
        "@data" => json_encode($data)
      ]);
      
      // Basic validation
      if (empty($data['description'])) {
        $this->messenger()->addError($this->t('Description is required'));
        return $this->redirectToReferer($data);
      }
      
      // Process browser info
      $browserInfo = isset($data["browser_info"]) ? $data["browser_info"] : "{}";
      if (is_string($browserInfo) && !empty($browserInfo)) {
        if (substr($browserInfo, 0, 1) !== "{" || json_decode($browserInfo) === NULL) {
          $browserInfo = json_encode(["raw_data" => $browserInfo]);
        }
      } else {
        $browserInfo = "{}";
      }

      $this->getLogger('tidy_feedback')->notice("Processing form with element selector: @selector", [
        "@selector" => isset($data["element_selector"]) ? $data["element_selector"] : '(none)'
      ]);
      
      // Get form values with defaults
      $url = isset($data["url"]) ? $data["url"] : $request->getUri();
      $elementSelector = isset($data["element_selector"]) ? $data["element_selector"] : "";
      $issueType = isset($data["issue_type"]) ? $data["issue_type"] : "other";
      $severity = isset($data["severity"]) ? $data["severity"] : "normal";
      
      // Process file attachment
      $attachment_fid = $this->processFileAttachment($files);
      
      // Insert into database
      $id = $this->database->insert('tidy_feedback')
        ->fields([
          'uuid' => $this->uuid->generate(),
          'uid' => $this->currentUser()->id(),
          'created' => $this->time->getRequestTime(),
          'changed' => $this->time->getRequestTime(),
          'issue_type' => $issueType,
          'severity' => $severity,
          'description__value' => $data['description'],
          'description__format' => 'basic_html',
          'url' => $url,
          'element_selector' => $elementSelector,
          'browser_info' => $browserInfo,
          'status' => 'new',
          'attachment__target_id' => $attachment_fid,
        ])
        ->execute();
      
      // Add file usage record if a file was attached
      if ($attachment_fid) {
        $this->fileUsage->add(
          File::load($attachment_fid),
          'tidy_feedback',
          'tidy_feedback',
          $id
        );
      }
      
      // Add success message
      $this->messenger()->addStatus($this->t('Thank you for your feedback. It has been submitted successfully.'));
      
      // Redirect to feedback list for administrators, or back to the original page for others
      if ($this->currentUser()->hasPermission('view tidy feedback reports')) {
        return new RedirectResponse(Url::fromRoute('entity.tidy_feedback.collection')->toString());
      } else {
        return $this->redirectToReferer($data);
      }
      
    } catch (\Exception $e) {
      // Log error
      $this->getLogger('tidy_feedback')->error(
        "Error processing feedback form: @error",
        ["@error" => $e->getMessage()]
      );
      
      // Display error message
      $this->messenger()->addError($this->t('An error occurred while processing your feedback. Please try again later.'));
      
      // Redirect back
      return $this->redirectToReferer($data);
    }
  }
  
  /**
   * Processes file attachment from the form.
   *
   * @param array $files
   *   The files array from the request.
   *
   * @return int|null
   *   The file ID of the attachment, or NULL if no file was attached.
   */
  protected function processFileAttachment(array $files) {
    $attachment_fid = NULL;
    
    if (!empty($files['files']['attachment'])) {
      $uploaded_file = $files['files']['attachment'];
      
      try {
        if ($uploaded_file->isValid()) {
          // Prepare directory
          $directory = 'public://tidy_feedback/attachments';
          if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
            throw new \Exception("Failed to create directory: $directory");
          }
          
          // Save file
          $filename = $uploaded_file->getClientOriginalName();
          $destination = $directory . '/' . $filename;
          
          $file_uri = $this->fileSystem->saveUploadedFile(
            $uploaded_file, 
            $destination,
            FileSystemInterface::EXISTS_RENAME
          );
          
          if (!$file_uri) {
            throw new \Exception("Failed to save uploaded file");
          }
          
          // Create file entity
          $file = File::create([
            'uri' => $file_uri,
            'uid' => $this->currentUser()->id(),
            'status' => FILE_STATUS_PERMANENT,
            'filename' => $this->fileSystem->basename($file_uri),
          ]);
          $file->save();
          
          $attachment_fid = $file->id();
          
          $this->getLogger('tidy_feedback')->notice(
            "File uploaded successfully: @filename (FID: @fid)",
            ["@filename" => $file->getFilename(), "@fid" => $attachment_fid]
          );
        } else {
          $this->getLogger('tidy_feedback')->error(
            "File upload error: @error",
            ["@error" => $uploaded_file->getError()]
          );
        }
      } catch (\Exception $e) {
        $this->getLogger('tidy_feedback')->error(
          "Error processing file attachment: @error",
          ["@error" => $e->getMessage()]
        );
        // Continue without the file
      }
    }
    
    return $attachment_fid;
  }
  
  /**
   * Redirects back to the referring page.
   *
   * @param array $data
   *   The form data array.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  protected function redirectToReferer(array $data) {
    // Try to get the URL from the form data
    if (!empty($data['url'])) {
      $url = urldecode($data['url']);
      
      // Simple redirect back to the URL
      try {
        return new RedirectResponse($url);
      }
      catch (\Exception $e) {
        $this->getLogger('tidy_feedback')->error("Invalid redirect URL: @url", [
          "@url" => $url
        ]);
      }
    }
    
    // Fallback to the front page
    return new RedirectResponse(Url::fromRoute('<front>')->toString());
  }

}