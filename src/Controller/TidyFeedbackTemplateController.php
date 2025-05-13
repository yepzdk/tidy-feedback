<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Template\Attribute;

/**
 * Controller for template-based feedback form.
 */
class TidyFeedbackTemplateController extends ControllerBase implements ContainerInjectionInterface {

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
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Constructs a new TidyFeedbackTemplateController.
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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(
    Connection $database,
    FileSystemInterface $file_system,
    FileUsageInterface $file_usage,
    EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $time,
    UuidInterface $uuid,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user,
    Renderer $renderer
  ) {
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->fileUsage = $file_usage;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->uuid = $uuid;
    $this->loggerFactory = $logger_factory;
    $this->currentUser = $current_user;
    $this->renderer = $renderer;
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
      $container->get('uuid'),
      $container->get('logger.factory'),
      $container->get('current_user'),
      $container->get('renderer')
    );
  }

  /**
   * Displays the template-based feedback form.
   *
   * @param string $element_selector
   *   The CSS selector of the element to provide feedback on.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The ajax response containing just the form.
   */
  public function displayForm($element_selector = '') {
    // Create form token for CSRF protection.
    $form_token = \Drupal::csrfToken()->get('tidy_feedback_template_form');
    
    // Get current URL.
    $current_url = \Drupal::request()->getUri();
    
    // Create empty browser info that will be filled by JavaScript.
    $browser_info = '{}';
    
    // Set up attributes for the container.
    $attributes = new Attribute();
    
    // Render the form using our template.
    $build = [
      '#theme' => 'tidy_feedback_form',
      '#attributes' => $attributes,
      '#form_token' => $form_token,
      '#current_url' => $current_url,
      '#element_selector' => $element_selector,
      '#browser_info' => $browser_info,
      '#attached' => [
        'library' => ['tidy_feedback/tidy_feedback_template_form'],
      ],
    ];
    
    // Render just the form content without the full page.
    $content = $this->renderer->renderRoot($build);
    
    // Create a response with just the form content.
    $response = new \Symfony\Component\HttpFoundation\Response($content);
    $response->headers->set('Content-Type', 'text/html');
    
    return $response;
  }

  /**
   * Handles form submissions from the template-based form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with submission status.
   */
  public function submitForm(Request $request) {
    try {
      // Get form data and files.
      $data = $request->request->all();
      $files = $request->files->all();
      
      // Log what we received for debugging.
      $this->getLogger('tidy_feedback')->notice("Template form data: @data", [
        "@data" => print_r($data, TRUE),
      ]);
      $this->getLogger('tidy_feedback')->notice("Template form files: @files", [
        "@files" => print_r($files, TRUE),
      ]);
      
      // Validate CSRF token if provided.
      if (isset($data['form_token']) && 
          !\Drupal::csrfToken()->validate($data['form_token'], 'tidy_feedback_template_form')) {
        throw new \Exception('Invalid security token');
      }
      
      // Basic validation.
      if (empty($data['description'])) {
        throw new \Exception($this->t('Description is required'));
      }
      
      // Process browser info - it might be a JSON string that needs decoding.
      $browserInfo = isset($data["browser_info"]) ? $data["browser_info"] : "";
      if (is_string($browserInfo) && !empty($browserInfo)) {
        // Check if it's already a JSON string and store as is.
        if (
          substr($browserInfo, 0, 1) === "{" &&
          json_decode($browserInfo) !== NULL
        ) {
          // It's already valid JSON, keep as is.
        }
        else {
          // Convert to JSON if it's not already.
          $browserInfo = json_encode(["raw_data" => $browserInfo]);
        }
      }
      else {
        // If empty or not a string, create an empty JSON object.
        $browserInfo = "{}";
      }
      
      // Get values with defaults.
      $url = isset($data["url"]) ? $data["url"] : \Drupal::request()->getUri();
      $issueType = isset($data["issue_type"]) ? $data["issue_type"] : "other";
      $severity = isset($data["severity"]) ? $data["severity"] : "normal";
      $elementSelector = isset($data["element_selector"]) ? $data["element_selector"] : "";
      
      // Handle file attachment.
      $attachment_fid = NULL;
      
      if (!empty($files['files']['attachment'])) {
        $uploaded_file = $files['files']['attachment'];
        
        try {
          if ($uploaded_file->isValid()) {
            // Prepare the directory.
            $directory = 'public://tidy_feedback/attachments';
            if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
              throw new \Exception("Failed to create directory: $directory");
            }
            
            // Get the filename and create a destination.
            $filename = $uploaded_file->getClientOriginalName();
            $destination = $directory . '/' . $filename;
            
            // Save the file with the file system service.
            $file_uri = $this->fileSystem->saveUploadedFile(
              $uploaded_file, 
              $destination,
              FileSystemInterface::EXISTS_RENAME
            );
            
            if (!$file_uri) {
              throw new \Exception("Failed to save uploaded file to $destination");
            }
            
            // Create a permanent file entity.
            $file = File::create([
              'uri' => $file_uri,
              'uid' => $this->currentUser->id(),
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
            throw new \Exception('File upload error: ' . $uploaded_file->getError());
          }
        } catch (\Exception $e) {
          $this->getLogger('tidy_feedback')->error(
            "Error uploading file: @error",
            ["@error" => $e->getMessage()]
          );
          // Continue without the file.
        }
      }
      
      // Insert feedback into the database.
      $id = $this->database->insert('tidy_feedback')
        ->fields([
          'uuid' => $this->uuid->generate(),
          'uid' => $this->currentUser->id(),
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
      
      // Record file usage if a file was attached.
      if ($attachment_fid) {
        $this->fileUsage->add(
          File::load($attachment_fid),
          'tidy_feedback',
          'tidy_feedback',
          $id
        );
      }
      
      $this->getLogger('tidy_feedback')->notice(
        "Feedback #@id submitted successfully via template form.",
        ["@id" => $id]
      );
      
      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Feedback submitted successfully'),
        'id' => $id,
        'show_message' => true,
      ]);
      
    } catch (\Exception $e) {
      $this->getLogger('tidy_feedback')->error(
        "Template form error: @error\nTrace: @trace",
        [
          "@error" => $e->getMessage(),
          "@trace" => $e->getTraceAsString(),
        ]
      );
      
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Error submitting feedback: @error', ['@error' => $e->getMessage()]),
      ], 500);
    }
  }

}