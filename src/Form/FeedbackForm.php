<?php

namespace Drupal\tidy_feedback\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for submitting feedback.
 */
class FeedbackForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tidy_feedback_form';
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
    Connection $database,
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="tidy-feedback-form-wrapper">';
    $form['#suffix'] = '</div>';

    // Set empty action to prevent redirect.
    $form['#action'] = '';

    // Add form ID.
    $form['#id'] = 'tidy-feedback-form';

    // Add enctype for file uploads.
    $form['#attributes']['enctype'] = 'multipart/form-data';

    $form['issue_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Issue Type'),
      '#options' => [
        'bug' => $this->t('Bug'),
        'enhancement' => $this->t('Enhancement'),
        'question' => $this->t('Question'),
        'other' => $this->t('Other'),
      ],
      '#required' => TRUE,
    ];

    $form['severity'] = [
      '#type' => 'select',
      '#title' => $this->t('Severity'),
      '#options' => [
        'critical' => $this->t('Critical'),
        'high' => $this->t('High'),
        'normal' => $this->t('Normal'),
        'low' => $this->t('Low'),
      ],
      '#default_value' => 'normal',
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t(
              'Please describe the issue or suggestion in detail.'
      ),
      '#rows' => 5,
      '#required' => TRUE,
    ];

    $form['file_attachment'] = [
      '#type' => 'file',
      '#title' => $this->t('Attachment'),
      '#description' => $this->t('Upload a screenshot or document related to this feedback (optional). Maximum size: 2MB.'),
      '#attributes' => ['id' => 'tidy-feedback-file'],
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png gif pdf doc docx txt'],
        'file_validate_size' => [2 * 1024 * 1024],
      ],
    ];

    // Hidden fields to store element information.
    $form['url'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'tidy-feedback-url'],
    ];

    $form['element_selector'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'tidy-feedback-element-selector'],
    ];

    $form['browser_info'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'tidy-feedback-browser-info'],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Feedback'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#ajax' => [
        'callback' => '::submitAjax',
        'wrapper' => 'tidy-feedback-form-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Submitting feedback...'),
        ],
      ],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#ajax' => [
        'callback' => '::cancelAjax',
        'wrapper' => 'tidy-feedback-form-wrapper',
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
      try {
        $id = $this->processFormSubmission($form_state);
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new InvokeCommand(NULL, 'tidyFeedbackSuccess', [$id]));
      }
      catch (\Exception $e) {
        // Add error message.
        $message = '<div class="messages messages--error">' . $this->t('Error submitting feedback: @error', ['@error' => $e->getMessage()]) . '</div>';
        $response->addCommand(new HtmlCommand('#tidy-feedback-form-wrapper', $message . render($form)));
      }
    }

    return $response;
  }

  /**
   * AJAX callback for cancel button.
   */
  public function cancelAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('description'))) {
      $form_state->setErrorByName(
            'description',
            $this->t('Description field is required.')
        );
    }

    if (empty($form_state->getValue('url'))) {
      $form_state->setValue('url', \Drupal::request()->getUri());
    }

    // Validate the uploaded file if one was provided.
    $file_upload = $this->getRequest()->files->get('file_attachment');
    if ($file_upload && $file_upload->getError() != UPLOAD_ERR_NO_FILE) {
      // Check file errors.
      if ($file_upload->getError() != UPLOAD_ERR_OK) {
        $form_state->setErrorByName('file_attachment', $this->t('File upload error: @error', [
          '@error' => $this->getUploadErrorMessage($file_upload->getError()),
        ]));
        return;
      }

      // Check file size (2MB limit)
      if ($file_upload->getSize() > 2 * 1024 * 1024) {
        $form_state->setErrorByName('file_attachment', $this->t('The file exceeds the maximum allowed size of 2MB.'));
      }

      // Store the uploaded file in the form state for use in submitForm.
      $form_state->set('file_upload', $file_upload);
    }
  }

  /**
   * Get human-readable message for upload error code.
   *
   * @param int $error_code
   *   The PHP file upload error code.
   *
   * @return string
   *   Human-readable error message.
   */
  protected function getUploadErrorMessage($error_code) {
    switch ($error_code) {
      case UPLOAD_ERR_INI_SIZE:
        return $this->t('The file exceeds the maximum upload size allowed by the server.');

      case UPLOAD_ERR_FORM_SIZE:
        return $this->t('The file exceeds the maximum upload size allowed by the form.');

      case UPLOAD_ERR_PARTIAL:
        return $this->t('The file was only partially uploaded.');

      case UPLOAD_ERR_NO_FILE:
        return $this->t('No file was uploaded.');

      case UPLOAD_ERR_NO_TMP_DIR:
        return $this->t('The server is missing a temporary folder.');

      case UPLOAD_ERR_CANT_WRITE:
        return $this->t('The server failed to write the file to disk.');

      case UPLOAD_ERR_EXTENSION:
        return $this->t('A PHP extension stopped the file upload.');

      default:
        return $this->t('An unknown error occurred during file upload.');
    }
  }

  /**
   * Process the form submission (separated to be called from AJAX callback).
   */
  protected function processFormSubmission(FormStateInterface $form_state) {
    try {
      // Get values.
      $values = $form_state->getValues();

      // Handle file upload if present.
      $file_path = NULL;
      $file_upload = $form_state->get('file_upload');
      if ($file_upload) {
        // Prepare directory.
        $directory = 'public://tidy_feedback/attachments';
        if (!\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
          throw new \Exception('Could not prepare directory for file attachments.');
        }

        // Generate unique filename.
        $timestamp = \Drupal::time()->getRequestTime();
        $filename = $timestamp . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file_upload->getClientOriginalName());
        $destination = $directory . '/' . $filename;

        // Move the uploaded file.
        if (!\Drupal::service('file_system')->moveUploadedFile($file_upload->getRealPath(), $destination)) {
          throw new \Exception('Could not save the uploaded file.');
        }

        $file_path = $destination;
        \Drupal::logger('tidy_feedback')->notice('File uploaded to @path', ['@path' => $file_path]);
      }

      // Create a record in the database.
      $connection = \Drupal::database();
      $id = $connection
        ->insert('tidy_feedback')
        ->fields([
          'uuid' => \Drupal::service('uuid')->generate(),
          'uid' => \Drupal::currentUser()->id(),
          'created' => \Drupal::time()->getRequestTime(),
          'changed' => \Drupal::time()->getRequestTime(),
          'issue_type' => $values['issue_type'],
          'severity' => $values['severity'],
          'description__value' => $values['description'],
          'description__format' => 'basic_html',
          'url' => $values['url'],
          'element_selector' => $values['element_selector'],
          'browser_info' => $values['browser_info'],
          'status' => 'new',
          'file_attachment' => $file_path,
        ])
        ->execute();

      \Drupal::logger('tidy_feedback')->notice(
            'Feedback #@id submitted successfully via form.',
            ['@id' => $id]
        );

      return $id;
    }
    catch (\Exception $e) {
      \Drupal::logger('tidy_feedback')->error(
            'Error saving feedback: @error',
            ['@error' => $e->getMessage()]
            );
      // Re-throw so the AJAX handler can catch it.
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This runs for non-AJAX submissions.
    try {
      $this->processFormSubmission($form_state);
      $this->messenger()->addStatus(
            $this->t('Thank you for your feedback.')
        );

      // Get the original URL from the form.
      $url = $form_state->getValue('url');
      if (!empty($url)) {
        // Set redirect to original page.
        $form_state->setRedirectUrl(Url::fromUri($url));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError(
            $this->t('Unable to save feedback. Please try again later.')
            );
    }
  }

}
