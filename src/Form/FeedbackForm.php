<?php

namespace Drupal\tidy_feedback\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;

/**
 * Provides a form for submitting feedback.
 */
class FeedbackForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return "tidy_feedback_form";
    }

    /**
       * The messenger service.
       *
       * @var \Drupal\Core\Messenger\MessengerInterface
       */
      protected $messenger;

      /**
       * The database connection.
       *
       * @var \Drupal\Core\Database\Connection
       */
      protected $database;

      /**
       * Constructs a FeedbackForm object.
       *
       * @param \Drupal\Core\Messenger\MessengerInterface $messenger
       *   The messenger service.
       * @param \Drupal\Core\Database\Connection $database
       *   The database connection.
       */
      public function __construct(
        MessengerInterface $messenger,
        Connection $database
      ) {
        $this->messenger = $messenger;
        $this->database = $database;
      }

      /**
       * {@inheritdoc}
       */
      public static function create(ContainerInterface $container) {
        return new static(
          $container->get('messenger'),
          $container->get('database')
        );
      }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form["#prefix"] = '<div id="tidy-feedback-form-wrapper">';
        $form["#suffix"] = "</div>";

        // Set empty action to prevent redirect
        $form["#action"] = "";

        // Add form ID
        $form["#id"] = "tidy-feedback-form";

        $form["issue_type"] = [
            "#type" => "select",
            "#title" => $this->t("Issue Type"),
            "#options" => [
                "bug" => $this->t("Bug"),
                "enhancement" => $this->t("Enhancement"),
                "question" => $this->t("Question"),
                "other" => $this->t("Other"),
            ],
            "#required" => true,
        ];

        $form["severity"] = [
            "#type" => "select",
            "#title" => $this->t("Severity"),
            "#options" => [
                "critical" => $this->t("Critical"),
                "high" => $this->t("High"),
                "normal" => $this->t("Normal"),
                "low" => $this->t("Low"),
            ],
            "#default_value" => "normal",
            "#required" => true,
        ];

        $form["description"] = [
            "#type" => "textarea",
            "#title" => $this->t("Description"),
            "#description" => $this->t(
                "Please describe the issue or suggestion in detail."
            ),
            "#rows" => 5,
            "#required" => true,
        ];

        // Hidden fields to store element information
        $form["url"] = [
            "#type" => "hidden",
            "#attributes" => ["id" => "tidy-feedback-url"],
        ];

        $form["element_selector"] = [
            "#type" => "hidden",
            "#attributes" => ["id" => "tidy-feedback-element-selector"],
        ];

        $form["browser_info"] = [
            "#type" => "hidden",
            "#attributes" => ["id" => "tidy-feedback-browser-info"],
        ];

        $form["actions"] = [
            "#type" => "actions",
        ];

        $form["actions"]["submit"] = [
            "#type" => "submit",
            "#value" => $this->t("Submit Feedback"),
            "#attributes" => ["class" => ["button", "button--primary"]],
            "#ajax" => [
                "callback" => "::submitAjax",
                "wrapper" => "tidy-feedback-form-wrapper",
                "progress" => [
                    "type" => "throbber",
                    "message" => $this->t("Submitting feedback..."),
                ],
            ],
        ];

        $form["actions"]["cancel"] = [
            "#type" => "button",
            "#value" => $this->t("Cancel"),
            "#attributes" => ["class" => ["button"]],
            "#ajax" => [
                "callback" => "::cancelAjax",
                "wrapper" => "tidy-feedback-form-wrapper",
            ],
        ];

        return $form;
    }

    /**
     * AJAX callback for form submission.
     */
     public function submitAjax(array &$form, FormStateInterface $form_state) {
       $response = new AjaxResponse();

       if ($form_state->getErrors()) {
         $response->addCommand(new HtmlCommand('#tidy-feedback-form-wrapper', $form));
       }
       else {
         $response->addCommand(new CloseModalDialogCommand());
         $response->addCommand(new InvokeCommand(NULL, 'tidyFeedbackSuccess'));
       }

       return $response;
     }

    /**
     * AJAX callback for cancel button.
     */
    public function cancelAjax(array &$form, FormStateInterface $form_state)
    {
        $response = new AjaxResponse();
        $response->addCommand(new CloseModalDialogCommand());
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if (empty($form_state->getValue("description"))) {
            $form_state->setErrorByName(
                "description",
                $this->t("Description field is required.")
            );
        }

        if (empty($form_state->getValue("url"))) {
            $form_state->setValue("url", \Drupal::request()->getUri());
        }
    }

    /**
     * Process the form submission (separated to be called from AJAX callback).
     */
    protected function processFormSubmission(FormStateInterface $form_state)
    {
        try {
            // Get values
            $values = $form_state->getValues();

            // Create a record in the database
            $connection = \Drupal::database();
            $id = $connection
                ->insert("tidy_feedback")
                ->fields([
                    "uuid" => \Drupal::service("uuid")->generate(),
                    "uid" => \Drupal::currentUser()->id(),
                    "created" => \Drupal::time()->getRequestTime(),
                    "changed" => \Drupal::time()->getRequestTime(),
                    "issue_type" => $values["issue_type"],
                    "severity" => $values["severity"],
                    "description__value" => $values["description"],
                    "description__format" => "basic_html",
                    "url" => $values["url"],
                    "element_selector" => $values["element_selector"],
                    "browser_info" => $values["browser_info"],
                    "status" => "new",
                ])
                ->execute();

            // Log success but don't show messenger message (we'll show via JS)
            \Drupal::logger("tidy_feedback")->notice(
                "Feedback #@id submitted successfully.",
                ["@id" => $id]
            );
        } catch (\Exception $e) {
            \Drupal::logger("tidy_feedback")->error(
                "Error saving feedback: @error",
                ["@error" => $e->getMessage()]
            );
            throw $e; // Re-throw so the AJAX handler can catch it
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // This runs for non-AJAX submissions
        try {
            $this->processFormSubmission($form_state);
            $this->messenger()->addStatus(
                $this->t("Thank you for your feedback.")
            );

            // Get the original URL from the form
            $url = $form_state->getValue("url");
            if (!empty($url)) {
                // Set redirect to original page
                $form_state->setRedirectUrl(\Drupal\Core\Url::fromUri($url));
            }
        } catch (\Exception $e) {
            $this->messenger()->addError(
                $this->t("Unable to save feedback. Please try again later.")
            );
        }
    }
}
