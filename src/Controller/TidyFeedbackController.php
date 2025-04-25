<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for handling feedback operations.
 */
class TidyFeedbackController extends ControllerBase implements ContainerInjectionInterface {

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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructor for TidyFeedbackController.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    Connection $database,
    AccountProxyInterface $current_user,
    TimeInterface $time,
    UuidInterface $uuid
  ) {
    $this->loggerFactory = $logger_factory;
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->time = $time;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('database'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('uuid')
    );
  }

  /**
   * Saves feedback submission from the form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function saveFeedback(Request $request) {
    try {
      $data = json_decode($request->getContent(), TRUE);

      if (empty($data)) {
        $this->getLogger('tidy_feedback')->warning(
          "Empty data received in saveFeedback"
        );
        return new JsonResponse(
          ["status" => "error", "message" => "Invalid data submitted"],
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
      $description = isset($data["description"]) ? $data["description"] : '';
      $elementSelector = isset($data["element_selector"]) ? $data["element_selector"] : "";

      $id = $this->database
        ->insert("tidy_feedback")
        ->fields([
          "uuid" => $this->uuid->generate(),
          "uid" => $this->currentUser->id(),
          "created" => $this->time->getRequestTime(),
          "changed" => $this->time->getRequestTime(),
          "issue_type" => $issueType,
          "severity" => $severity,
          "description__value" => $description,
          "description__format" => "basic_html",
          "url" => $url,
          "element_selector" => $elementSelector,
          "browser_info" => $browserInfo,
          "status" => "new",
        ])
        ->execute();

      $this->getLogger('tidy_feedback')->notice(
        "Feedback #@id submitted successfully via saveFeedback.",
        ["@id" => $id]
      );

      return new JsonResponse([
        "status" => "success",
        "message" => $this->t("Feedback submitted successfully"),
        "id" => $id,
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('tidy_feedback')->error(
        "Error saving feedback: @error",
        ["@error" => $e->getMessage()]
      );
      return new JsonResponse(
        ["status" => "error", "message" => $e->getMessage()],
        500
      );
    }
  }

  /**
   * Overview page for the admin interface.
   *
   * @return array
   *   Render array for the admin overview page.
   */
  public function adminOverview() {
    // This is a basic controller method that just redirects to the View
    // we'll create for displaying feedback items.

    $build = [
      "#markup" => $this->t(
        "The Tidy Feedback administration interface is provided by a View. If you do not see it below, please ensure the View is properly configured."
      ),
    ];

    // Embed the view in the page.
    $view = views_embed_view("tidy_feedback_list", "default");
    if ($view) {
      $build["view"] = $view;
    }

    return $build;
  }

  /**
   * Gets the title for the feedback canonical page.
   *
   * @param object $tidy_feedback
   *   The feedback entity.
   *
   * @return string
   *   The page title.
   */
  public function getTitle($tidy_feedback) {
    return $this->t("Feedback #@id", ["@id" => $tidy_feedback->id()]);
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
      // Check for JSON content type.
      $contentType = $request->headers->get("Content-Type");
      if (strpos($contentType, "application/json") !== FALSE) {
        $data = json_decode($request->getContent(), TRUE);
      }
      else {
        $data = $request->request->all();
      }

      $this->getLogger("tidy_feedback")->notice(
        "Received data type: @type",
        [
          "@type" => gettype($data),
        ]
      );

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
        ])
        ->execute();

      $this->getLogger("tidy_feedback")->notice(
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
      $this->getLogger("tidy_feedback")->error(
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
