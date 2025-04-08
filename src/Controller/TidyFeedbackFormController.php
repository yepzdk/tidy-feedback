<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for handling feedback form operations.
 */
class TidyFeedbackFormController extends ControllerBase
{
    /**
     * The form builder.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * Constructs a TidyFeedbackFormController object.
     *
     * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
     *   The form builder.
     */
    public function __construct(FormBuilderInterface $form_builder)
    {
        $this->formBuilder = $form_builder;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static($container->get("form_builder"));
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
            \Drupal::logger("tidy_feedback")->notice(
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
            \Drupal::logger("tidy_feedback")->error(
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

            \Drupal::logger("tidy_feedback")->notice("Received data: @data", [
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
            $connection = \Drupal::database();
            $id = $connection
                ->insert("tidy_feedback")
                ->fields([
                    "uuid" => \Drupal::service("uuid")->generate(),
                    "uid" => \Drupal::currentUser()->id(),
                    "created" => \Drupal::time()->getRequestTime(),
                    "changed" => \Drupal::time()->getRequestTime(),
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
