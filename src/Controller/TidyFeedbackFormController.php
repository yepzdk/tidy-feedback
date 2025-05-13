<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Controller for handling feedback form operations.
 */
class TidyFeedbackFormController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructor for TidyFeedbackFormController.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    FormBuilderInterface $form_builder,
    Connection $database,
    TimeInterface $time,
    UuidInterface $uuid,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user,
    FileSystemInterface $file_system
  ) {
    $this->formBuilder = $form_builder;
    $this->database = $database;
    $this->time = $time;
    $this->uuid = $uuid;
    $this->loggerFactory = $logger_factory;
    $this->currentUser = $current_user;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('uuid'),
      $container->get('logger.factory'),
      $container->get('current_user'),
      $container->get('file_system')
    );
  }

  /**
   * Returns the feedback form.
   *
   * @return array|Response
   *   A render array containing the feedback form.
   */
  public function getForm() {
    try {
      // Log that we're attempting to get the form.
      $this->getLogger('tidy_feedback')->notice(
        "Attempting to load feedback form"
      );

      // Build the form.
      $form = $this->formBuilder->getForm(
        'Drupal\tidy_feedback\Form\FeedbackForm'
      );

      // Return as a render array.
      return $form;
    }
    catch (\Exception $e) {
      // Log the error.
      $this->getLogger('tidy_feedback')->error(
        "Error loading feedback form: @error",
        ["@error" => $e->getMessage()]
      );

      // Return a simple error message.
      return [
        "#markup" => $this->t(
          "Error loading feedback form. Please check the logs for details."
        ),
      ];
    }
  }

  /**
   * Controller method to handle direct form submissions.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with submission status.
   */
  public function submitDirectFeedback(Request $request) {
    try {
      // Get data from the request
      $data = $request->request->all();
      $files = $request->files->all();
      
      // Check for JSON content type (for backward compatibility)
      $contentType = $request->headers->get("Content-Type");
      if (strpos($contentType, "application/json") !== FALSE && empty($data)) {
        $data = json_decode($request->getContent(), TRUE);
      }

      $this->getLogger('tidy_feedback')->notice("Received data: @data", [
        "@data" => print_r($data, TRUE),
      ]);

      // Validate required fields.
      if (empty($data["description"])) {
        return new JsonResponse(
          [
            "status" => "error",
            "message" => "Description is required",
          ],
          400
        );
      }

      // Process browser_info - it might be a JSON string that needs decoding.
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

      $referer = $request->headers->get("referer");
      $url = isset($data["url"]) ? $data["url"] : ($referer ?: '');
      $issueType = isset($data["issue_type"]) ? $data["issue_type"] : "other";
      $severity = isset($data["severity"]) ? $data["severity"] : "normal";
      $elementSelector = isset($data["element_selector"]) ? $data["element_selector"] : "";

      // Process file attachment if present
      $attachment_fid = NULL;
      if (!empty($files['attachment'])) {
        $uploaded_file = $files['attachment'];
        if ($uploaded_file->isValid()) {
          // Prepare the destination directory
          $directory = 'public://tidy_feedback/attachments';
          $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
          
          // Save the file
          $file = File::create([
            'uri' => $this->fileSystem->saveUploadedFile($uploaded_file, $directory),
            'uid' => $this->currentUser->id(),
            'status' => FILE_STATUS_PERMANENT,
          ]);
          $file->save();
          
          $attachment_fid = $file->id();
          $this->getLogger('tidy_feedback')->notice(
            "File uploaded successfully: @filename (FID: @fid)",
            ["@filename" => $file->getFilename(), "@fid" => $attachment_fid]
          );
        }
      }
      
      // Insert into database.
      $id = $this->database
        ->insert("tidy_feedback")
        ->fields([
          "uuid" => $this->uuid->generate(),
          "uid" => $this->currentUser->id(),
          "created" => $this->time->getRequestTime(),
          "changed" => $this->time->getRequestTime(),
          "issue_type" => $issueType,
          "severity" => $severity,
          "description__value" => $data["description"],
          "description__format" => "basic_html",
          "url" => $url,
          "element_selector" => $elementSelector,
          "browser_info" => $browserInfo,
          "status" => "new",
          "attachment__target_id" => $attachment_fid,
        ])
        ->execute();
        
      // If a file was attached, add file usage record
      if ($attachment_fid) {
        \Drupal::service('file.usage')->add(
          File::load($attachment_fid),
          'tidy_feedback',
          'tidy_feedback',
          $id
        );
      }

      $this->getLogger('tidy_feedback')->notice(
        "Feedback #@id submitted successfully via direct controller.",
        ["@id" => $id]
      );

      return new JsonResponse([
        "status" => "success",
        "message" => "Feedback submitted successfully",
        "id" => $id,
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('tidy_feedback')->error(
        "Error saving feedback via direct controller: @error",
        ["@error" => $e->getMessage()]
      );
      return new JsonResponse(
        ["status" => "error", "message" => $e->getMessage()],
        500
      );
    }
  }

}
