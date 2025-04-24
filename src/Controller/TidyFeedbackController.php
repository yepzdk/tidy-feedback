<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\user\Entity\User;
use Drupal\Core\Time\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for handling feedback operations.
 */
class TidyFeedbackController extends ControllerBase implements ContainerInjectionInterface
{
    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * The time service.
     *
     * @var \Drupal\Core\Time\TimeInterface
     */
    protected $time;

    /**
     * The UUID service.
     *
     * @var \Drupal\Component\Uuid\UuidInterface
     */
    protected $uuid;

    /**
     * Constructs a TidyFeedbackController object.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   The database connection.
     * @param \Drupal\Core\Time\TimeInterface $time
     *   The time service.
     * @param \Drupal\Component\Uuid\UuidInterface $uuid
     *   The UUID service.
     */
    public function __construct(Connection $database, TimeInterface $time, UuidInterface $uuid) {
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
     * Saves feedback submission from the form.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *   JSON response indicating success or failure.
     */
    public function saveFeedback(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data)) {
            return new JsonResponse(
                ["status" => "error", "message" => "Invalid data submitted"],
                400
            );
        }

        try {
            $this->database
                ->insert("tidy_feedback")
                ->fields([
                    "uuid" => $this->uuid->generate(),
                    "uid" => $this->currentUser()->id(),
                    "created" => $this->time->getRequestTime(),
                    "changed" => $this->time->getRequestTime(),
                    "issue_type" => $data["issue_type"],
                    "severity" => $data["severity"],
                    "description__value" => $data["description"],
                    "description__format" => "basic_html",
                    "url" => $data["url"],
                    "element_selector" => $data["element_selector"],
                    "browser_info" => $data["browser_info"],
                    "status" => "new",
                ])
                ->execute();

            return new JsonResponse([
                "status" => "success",
                "message" => $this->t("Feedback submitted successfully"),
            ]);
        } catch (\Exception $e) {
            \Drupal::logger("tidy_feedback")->error(
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
    public function adminOverview()
    {
        // This is a basic controller method that just redirects to the View
        // we'll create for displaying feedback items.

        $build = [
            "#markup" => $this->t(
                "The Tidy Feedback administration interface is provided by a View. If you do not see it below, please ensure the View is properly configured."
            ),
        ];

        // Embed the view in the page
        $view = views_embed_view("tidy_feedback_list", "default");
        if ($view) {
            $build["view"] = $view;
        }

        return $build;
    }

    /**
     * Gets the title for the feedback canonical page.
     *
     * @param \Drupal\tidy_feedback\Entity\Feedback $tidy_feedback
     *   The feedback entity.
     *
     * @return string
     *   The page title.
     */
    public function getTitle($tidy_feedback)
    {
        return $this->t("Feedback #@id", ["@id" => $tidy_feedback->id()]);
    }

    /**
     * Controller method to handle direct form submissions.
     */
    public function submitDirectFeedback(Request $request)
    {
        try {
            // Check for JSON content type
            $contentType = $request->headers->get("Content-Type");
            if (strpos($contentType, "application/json") !== false) {
                $data = json_decode($request->getContent(), true);
            } else {
                $data = $request->request->all();
            }

            \Drupal::logger("tidy_feedback")->notice(
                "Received data type: @type",
                [
                    "@type" => gettype($data),
                ]
            );

            // Validate required fields
            if (empty($data["description"])) {
                return new JsonResponse(
                    [
                        "status" => "error",
                        "message" => "Description is required",
                    ],
                    400
                );
            }

            // Process browser_info - it might be a JSON string that needs decoding
            $browserInfo = $data["browser_info"] ?? "";
            if (is_string($browserInfo) && !empty($browserInfo)) {
                // Check if it's already a JSON string and store as is
                if (
                    substr($browserInfo, 0, 1) === "{" &&
                    json_decode($browserInfo) !== null
                ) {
                    // It's already valid JSON, keep as is
                } else {
                    // Convert to JSON if it's not already
                    $browserInfo = json_encode(["raw_data" => $browserInfo]);
                }
            } else {
                // If empty or not a string, create an empty JSON object
                $browserInfo = "{}";
            }

            // Insert into database
            $id = $this->database
                ->insert("tidy_feedback")
                ->fields([
                    "uuid" => $this->uuid->generate(),
                    "uid" => $this->currentUser()->id(),
                    "created" => $this->time->getRequestTime(),
                    "changed" => $this->time->getRequestTime(),
                    "issue_type" => $data["issue_type"] ?? "other",
                    "severity" => $data["severity"] ?? "normal",
                    "description__value" => $data["description"],
                    "description__format" => "basic_html",
                    "url" => $data["url"] ?? $request->headers->get("referer"),
                    "element_selector" => $data["element_selector"] ?? "",
                    "browser_info" => $browserInfo,
                    "status" => "new",
                ])
                ->execute();

            \Drupal::logger("tidy_feedback")->notice(
                "Feedback #@id submitted successfully via direct controller.",
                ["@id" => $id]
            );

            return new JsonResponse([
                "status" => "success",
                "message" => "Feedback submitted successfully",
                "id" => $id,
            ]);
        } catch (\Exception $e) {
            \Drupal::logger("tidy_feedback")->error(
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
