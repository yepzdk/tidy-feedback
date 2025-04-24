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
use Drupal\Core\Time\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;

/**
 * Controller for handling feedback form operations.
 */
class TidyFeedbackFormController extends ControllerBase implements ContainerInjectionInterface
{
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
     * Constructs a TidyFeedbackFormController object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder.
     * @param \Drupal\Core\Database\Connection $database
     *   The database connection.
     * @param \Drupal\Core\Time\TimeInterface $time
     *   The time service.
     * @param \Drupal\Component\Uuid\UuidInterface $uuid
     *   The UUID service.
     */
    public function __construct(
        FormBuilderInterface $form_builder,
        Connection $database,
        TimeInterface $time,
        UuidInterface $uuid
    ) {
        $this->formBuilder = $form_builder;
        $this->database = $database;
        $this->time = $time;
        $this->uuid = $uuid;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('form_builder'),
            $container->get('database'),
            $container->get('datetime.time'),
            $container->get('uuid')
        );
    }

    /**
     * Returns the feedback form.
     *
     * @return array|Response
     *   A render array containing the feedback form.
     */
    public function getForm()
    {
        try {
            // Log that we're attempting to get the form
            $this->getLogger('tidy_feedback')->notice(
                "Attempting to load feedback form"
            );

            // Build the form
            $form = $this->formBuilder->getForm(
                'Drupal\tidy_feedback\Form\FeedbackForm'
            );

            // Return as a render array
            return $form;
        } catch (\Exception $e) {
            // Log the error
            $this->getLogger('tidy_feedback')->error(
                "Error loading feedback form: @error",
                ["@error" => $e->getMessage()]
            );

            // Return a simple error message
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

            $this->getLogger('tidy_feedback')->notice("Received data: @data", [
                "@data" => print_r($data, true),
            ]);

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
                    "browser_info" => $data["browser_info"] ?? "",
                    "status" => "new",
                ])
                ->execute();

            $this->getLogger('tidy_feedback')->notice(
                "Feedback #@id submitted successfully via direct controller.",
                ["@id" => $id]
            );

            return new JsonResponse([
                "status" => "success",
                "message" => "Feedback submitted successfully",
                "id" => $id,
            ]);
        } catch (\Exception $e) {
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
