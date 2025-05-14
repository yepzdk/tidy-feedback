<?php

namespace Drupal\tidy_feedback\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Url;

/**
 * Provides a form for submitting feedback.
 */
class TidyFeedbackForm extends FormBase {

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
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new TidyFeedbackForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    Connection $database,
    TimeInterface $time,
    UuidInterface $uuid,
    FileSystemInterface $file_system
  ) {
    $this->database = $database;
    $this->time = $time;
    $this->uuid = $uuid;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('uuid'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tidy_feedback_form';
  }
  
  /**
   * Checks if a URL is valid.
   * 
   * @param string $url
   *   The URL to validate.
   * 
   * @return bool
   *   TRUE if the URL is valid, FALSE otherwise.
   */
  protected function isValidUrl($url) {
    // Basic URL validation - allow both absolute and relative URLs
    if (empty($url)) {
      return FALSE;
    }
    
    // Allow internal Drupal paths that don't validate as URLs
    if (strpos($url, '/') === 0) {
      return TRUE;
    }
    
    return filter_var($url, FILTER_VALIDATE_URL) !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get URL parameters.
    $request = $this->getRequest();
    $element_selector = $request->query->get('element_selector', '');
    $url = $request->query->get('url', '');
    
    // Set form attributes
    $form['#attributes'] = [
      'data-drupal-form-submit-last' => TRUE,
      'id' => 'tidy-feedback-form',
    ];
    
    // Set direct form action
    $form['#action'] = \Drupal::request()->getRequestUri();
    
    // Create container for intro information.
    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['tidy-feedback-info'],
      ],
    ];
    
    // Show selected element if available.
    if (!empty($element_selector)) {
      $form['intro']['element_info'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => '<strong>' . $this->t('Selected element:') . '</strong> ' . $element_selector,
        '#attributes' => [
          'class' => ['tidy-feedback-element-info'],
        ],
      ];
    }
    
    // Show source URL if available.
    if (!empty($url)) {
      $form['intro']['url_info'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => '<strong>' . $this->t('Page:') . '</strong> <a href="' . $url . '">' . $url . '</a>',
        '#attributes' => [
          'class' => ['tidy-feedback-url-info'],
        ],
      ];
    }
    
    // Issue type field.
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
      '#default_value' => 'other',
    ];
    
    // Severity field.
    $form['severity'] = [
      '#type' => 'select',
      '#title' => $this->t('Severity'),
      '#options' => [
        'critical' => $this->t('Critical'),
        'high' => $this->t('High'),
        'normal' => $this->t('Normal'),
        'low' => $this->t('Low'),
      ],
      '#required' => TRUE,
      '#default_value' => 'normal',
    ];
    
    // Description field.
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Please describe the issue or suggestion in detail.'),
      '#rows' => 5,
      '#required' => TRUE,
    ];
    
    // Make sure the upload directory exists
    $directory = 'public://tidy_feedback/attachments';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    
    // Log directory status
    $this->logger('tidy_feedback')->notice('Upload directory status: @directory - exists: @exists, writable: @writable', [
      '@directory' => $directory,
      '@exists' => file_exists(\Drupal::service('file_system')->realpath($directory)) ? 'Yes' : 'No',
      '@writable' => is_writable(\Drupal::service('file_system')->realpath($directory)) ? 'Yes' : 'No',
    ]);
    
    // File attachment field.
    $form['attachment'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Attachment'),
      '#description' => $this->t('Upload a file to provide additional context (optional).'),
      '#upload_location' => $directory,
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png gif pdf doc docx xls xlsx txt csv'],
        'file_validate_size' => [5 * 1024 * 1024], // 5MB
      ],
      // Ensure the form has the correct encoding type
      '#attached' => [
        'library' => ['core/drupal.ajax'],
      ],
    ];
    
    // Hidden fields for context.
    $form['element_selector'] = [
      '#type' => 'hidden',
      '#value' => $element_selector,
    ];
    
    $form['url'] = [
      '#type' => 'hidden',
      '#value' => $url,
    ];
    
    // Collect browser info using JavaScript.
    $form['browser_info'] = [
      '#type' => 'hidden',
      '#default_value' => '{}',
      '#attributes' => [
        'id' => 'tidy-feedback-browser-info',
      ],
    ];
    
    // Add hidden token field for CSRF protection
    $form['token'] = [
      '#type' => 'hidden',
      '#value' => \Drupal::csrfToken()->get('tidy_feedback_form'),
    ];
    
    // Add JavaScript to collect browser info.
    $form['#attached']['library'][] = 'tidy_feedback/tidy_feedback_form_page';
    
    // Submit button.
    $form['actions'] = [
      '#type' => 'actions',
    ];
    
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit Feedback'),
    ];
    
    // Cancel link.
    if (!empty($url)) {
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromUri($url),
        '#attributes' => [
          'class' => ['button', 'button--secondary'],
        ],
      ];
    }
    else {
      $form['actions']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute('<front>'),
        '#attributes' => [
          'class' => ['button', 'button--secondary'],
        ],
      ];
    }
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Enable display of detailed errors for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    $this->logger('tidy_feedback')->notice('Form validation triggered');
    
    // Log all form values for debugging
    $values = $form_state->getValues();
    $this->logger('tidy_feedback')->notice('Form values: @values', [
      '@values' => print_r($values, TRUE),
    ]);
    
    // Log information about the file upload fields
    $this->logger('tidy_feedback')->notice('File upload field in form: @field', [
      '@field' => isset($form['attachment']) ? 'Yes' : 'No',
    ]);
    
    // Check for file upload
    $attachment = $form_state->getValue('attachment');
    $this->logger('tidy_feedback')->notice('Attachment in form_state: @attachment', [
      '@attachment' => print_r($attachment, TRUE),
    ]);
    
    // Check if we have files in the request
    $request = $this->getRequest();
    $this->logger('tidy_feedback')->notice('Files in request: @files', [
      '@files' => print_r($request->files->all(), TRUE),
    ]);
    
    // Validate description is not empty.
    if (empty($form_state->getValue('description'))) {
      $form_state->setErrorByName('description', $this->t('Description is required.'));
      $this->logger('tidy_feedback')->notice('Validation failed: description is empty');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Enable display of detailed errors for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    // Add a debug message to track submission
    $this->messenger()->addStatus($this->t('Form submission received!'));
    
    try {
      // Extract form values.
      $element_selector = $form_state->getValue('element_selector');
      $url = $form_state->getValue('url');
      $description = $form_state->getValue('description');
      $issue_type = $form_state->getValue('issue_type');
      $severity = $form_state->getValue('severity');
      $browser_info = $form_state->getValue('browser_info') ?? '{}';
      $attachment_fid = NULL;
      
      // Log form values for debugging
      $this->logger('tidy_feedback')->notice('Form values: @values', [
        '@values' => print_r($form_state->getValues(), TRUE),
      ]);
      
      // Process file attachment.
      $attachment = $form_state->getValue('attachment');
      $this->logger('tidy_feedback')->notice('Attachment value: @attachment', [
        '@attachment' => print_r($attachment, TRUE),
      ]);
      
      if (!empty($attachment[0])) {
        $attachment_fid = $attachment[0];
        
        // Set file as permanent.
        $file = File::load($attachment_fid);
        $this->logger('tidy_feedback')->notice('Loaded file: @file', [
          '@file' => $file ? 'File ID ' . $file->id() : 'No file found',
        ]);
        
        if ($file) {
          $file->setPermanent();
          $file->save();
          $this->logger('tidy_feedback')->notice('File saved as permanent: @uri', [
            '@uri' => $file->getFileUri(),
          ]);
        }
      }
      
      // Insert feedback into database.
      try {
        $fields = [
          'uuid' => $this->uuid->generate(),
          'uid' => $this->currentUser()->id(),
          'created' => $this->time->getRequestTime(),
          'changed' => $this->time->getRequestTime(),
          'issue_type' => $issue_type,
          'severity' => $severity,
          'description__value' => $description,
          'description__format' => 'basic_html',
          'url' => $url,
          'element_selector' => $element_selector,
          'browser_info' => $browser_info,
          'status' => 'new',
          'attachment__target_id' => $attachment_fid,
        ];
        
        $this->logger('tidy_feedback')->notice('Inserting feedback with fields: @fields', [
          '@fields' => print_r($fields, TRUE),
        ]);
        
        $id = $this->database->insert('tidy_feedback')
          ->fields($fields)
          ->execute();
        
        // Add file usage record if file was attached.
        if ($attachment_fid) {
          $file = File::load($attachment_fid);
          $this->logger('tidy_feedback')->notice('Adding file usage for file: @file', [
            '@file' => $file ? 'File ID ' . $file->id() : 'No file found',
          ]);
          
          \Drupal::service('file.usage')->add(
            $file,
            'tidy_feedback',
            'tidy_feedback',
            $id
          );
        }
      }
      catch (\Exception $e) {
        $this->logger('tidy_feedback')->error('Database error: @error', [
          '@error' => $e->getMessage(),
        ]);
        throw $e;
      }
      
      // Log success.
      $this->logger('tidy_feedback')->notice('Feedback submitted with ID: @id', [
        '@id' => $id,
      ]);
      
      // Set success message.
      $this->messenger()->addStatus($this->t('Thank you for your feedback. It has been submitted successfully with ID: @id', ['@id' => $id]));
      
      // Log redirect information
      $this->logger('tidy_feedback')->notice('Attempting redirect after submission. URL: @url', [
        '@url' => $url,
      ]);
      
      // Redirect based on user permissions.
      if ($this->currentUser()->hasPermission('view tidy feedback reports')) {
        $this->logger('tidy_feedback')->notice('Redirecting to feedback collection.');
        $form_state->setRedirect('entity.tidy_feedback.collection');
      }
      elseif (!empty($url)) {
        $this->logger('tidy_feedback')->notice('Redirecting to originating URL: @url', ['@url' => $url]);
        
        try {
          // Force a redirect using response object for all URLs
          $response = new \Symfony\Component\HttpFoundation\RedirectResponse($url);
          $form_state->setResponse($response);
          
          // Force immediate redirect - this will bypass Drupal's redirect handling
          $response->send();
          exit();
        } catch (\Exception $e) {
          $this->logger('tidy_feedback')->error('Redirect error: @error', ['@error' => $e->getMessage()]);
          $form_state->setRedirect('<front>');
        }
      }
      else {
        $this->logger('tidy_feedback')->notice('Redirecting to front page.');
        $form_state->setRedirect('<front>');
      }
    }
    catch (\Exception $e) {
      // Log detailed error.
      $this->logger('tidy_feedback')->error('Error submitting feedback: @error. Trace: @trace', [
        '@error' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
      ]);
      
      // Output the error directly to the screen for debugging
      echo '<h1>Debug Error Information</h1>';
      echo '<p>Error: ' . $e->getMessage() . '</p>';
      echo '<pre>' . $e->getTraceAsString() . '</pre>';
      
      // Log request information
      $request = $this->getRequest();
      $this->logger('tidy_feedback')->notice('Request details: method=@method, content-type=@content_type', [
        '@method' => $request->getMethod(),
        '@content_type' => $request->headers->get('Content-Type'),
      ]);
      
      // Check if form has file upload field properties
      if (isset($form['attachment'])) {
        $this->logger('tidy_feedback')->notice('File field properties: @properties', [
          '@properties' => print_r($form['attachment'], TRUE),
        ]);
      }
      
      // Show detailed error message for debugging
      $this->messenger()->addError($this->t('Error: @msg', ['@msg' => $e->getMessage()]));
      $this->messenger()->addWarning($this->t('Please report this error to the site administrator.'));
      
      // Dump uploaded file details for debugging if available
      if (!empty($_FILES)) {
        echo '<h2>$_FILES Contents:</h2>';
        echo '<pre>' . print_r($_FILES, true) . '</pre>';
      }
      
      // Check temporary directory settings
      echo '<h2>Upload Directory Information:</h2>';
      echo '<p>Upload directory: ' . ini_get('upload_tmp_dir') . '</p>';
      echo '<p>System temp directory: ' . sys_get_temp_dir() . '</p>';
      echo '<p>Max upload size: ' . ini_get('upload_max_filesize') . '</p>';
      echo '<p>Post max size: ' . ini_get('post_max_size') . '</p>';
      
      // Stay on the form.
      $form_state->setRebuild();
    }
  }

}