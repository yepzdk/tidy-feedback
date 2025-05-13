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
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for direct feedback submission and testing.
 */
class TidyFeedbackDirectController extends ControllerBase implements ContainerInjectionInterface {

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
   * Constructs a new TidyFeedbackDirectController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    Connection $database,
    FileSystemInterface $file_system,
    FileUsageInterface $file_usage,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user
  ) {
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->fileUsage = $file_usage;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->currentUser = $current_user;
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
      $container->get('logger.factory'),
      $container->get('current_user')
    );
  }
  
  /**
   * Displays a test form for direct file upload testing.
   *
   * @return array
   *   The form render array.
   */
  public function testForm() {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['tidy-feedback-test-form-container']],
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
      'form' => [
        '#type' => 'html_tag',
        '#tag' => 'form',
        '#attributes' => [
          'id' => 'tidy-feedback-test-form',
          'enctype' => 'multipart/form-data',
          'action' => '/tidy-feedback/test-submit',
          'method' => 'post',
        ],
        'issue_type' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['form-item']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#attributes' => ['for' => 'issue_type'],
            '#value' => 'Issue Type',
          ],
          'field' => [
            '#type' => 'html_tag',
            '#tag' => 'select',
            '#attributes' => [
              'id' => 'issue_type',
              'name' => 'issue_type',
              'required' => 'required',
            ],
            'options' => [
              [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => ['value' => 'bug'],
                '#value' => 'Bug',
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => ['value' => 'enhancement'],
                '#value' => 'Enhancement',
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => ['value' => 'question'],
                '#value' => 'Question',
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => ['value' => 'other'],
                '#value' => 'Other',
              ],
            ],
          ],
        ],
        'severity' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['form-item']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#attributes' => ['for' => 'severity'],
            '#value' => 'Severity',
          ],
          'field' => [
            '#type' => 'html_tag',
            '#tag' => 'select',
            '#attributes' => [
              'id' => 'severity',
              'name' => 'severity',
              'required' => 'required',
            ],
            'options' => [
              [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => ['value' => 'critical'],
                '#value' => 'Critical',
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => ['value' => 'high'],
                '#value' => 'High',
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => ['value' => 'normal', 'selected' => 'selected'],
                '#value' => 'Normal',
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => ['value' => 'low'],
                '#value' => 'Low',
              ],
            ],
          ],
        ],
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['form-item']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#attributes' => ['for' => 'description'],
            '#value' => 'Description',
          ],
          'field' => [
            '#type' => 'html_tag',
            '#tag' => 'textarea',
            '#attributes' => [
              'id' => 'description',
              'name' => 'description',
              'rows' => '5',
              'required' => 'required',
            ],
          ],
        ],
        'attachment' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['form-item']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#attributes' => ['for' => 'attachment'],
            '#value' => 'Attachment',
          ],
          'field' => [
            '#type' => 'html_tag',
            '#tag' => 'input',
            '#attributes' => [
              'id' => 'attachment',
              'name' => 'files[attachment]',
              'type' => 'file',
              'accept' => 'image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv',
            ],
          ],
          'description' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class' => ['description']],
            '#value' => 'Upload a file to provide additional context (optional). Max size: 5MB.',
          ],
        ],
        'hidden' => [
          '#type' => 'html_tag',
          '#tag' => 'input',
          '#attributes' => [
            'type' => 'hidden',
            'id' => 'url',
            'name' => 'url',
            'value' => \Drupal::request()->getUri(),
          ],
        ],
        'token' => [
          '#type' => 'html_tag',
          '#tag' => 'input',
          '#attributes' => [
            'type' => 'hidden',
            'name' => 'form_token',
            'value' => \Drupal::csrfToken()->get('tidy_feedback_test_form'),
          ],
        ],
        'actions' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['form-actions']],
          'submit' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'type' => 'submit',
              'id' => 'submit',
              'class' => ['button', 'button--primary'],
            ],
            '#value' => 'Submit Feedback',
          ],
        ],
      ],
      'result' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['id' => 'test-result'],
      ],
      'js' => [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => "
          document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('tidy-feedback-test-form').addEventListener('submit', function(e) {
              e.preventDefault();
              
              var formData = new FormData(this);
              
              fetch('/tidy-feedback/test-submit', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
              })
              .then(response => response.json())
              .then(data => {
                document.getElementById('test-result').innerHTML = 
                  '<div class=\"messages messages--' + (data.status === 'success' ? 'status' : 'error') + '\">' + 
                  data.message + 
                  (data.details ? '<pre>' + data.details + '</pre>' : '') +
                  '</div>';
              })
              .catch(error => {
                document.getElementById('test-result').innerHTML = 
                  '<div class=\"messages messages--error\">Error: ' + error.message + '</div>';
              });
            });
          });
        ",
      ],
    ];
  }

  /**
   * Handles the test form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with submission status.
   */
  public function testSubmit(Request $request) {
    try {
      // Get form data
      $data = $request->request->all();
      $files = $request->files->all();
      
      // Log what we received
      $this->getLogger('tidy_feedback')->notice("Test form data: @data", [
        "@data" => print_r($data, TRUE),
      ]);
      $this->getLogger('tidy_feedback')->notice("Test form files: @files", [
        "@files" => print_r($files, TRUE),
      ]);
      
      // Validate CSRF token
      if (!isset($data['form_token']) || 
          !\Drupal::csrfToken()->validate($data['form_token'], 'tidy_feedback_test_form')) {
        throw new \Exception('Invalid token');
      }
      
      // Basic validation
      if (empty($data['description'])) {
        throw new \Exception('Description is required');
      }
      
      $attachment_fid = NULL;
      
      // Handle file upload
      if (!empty($files['files']['attachment'])) {
        $uploaded_file = $files['files']['attachment'];
        
        if ($uploaded_file->isValid()) {
          // Prepare directory
          $directory = 'public://tidy_feedback/attachments';
          if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
            throw new \Exception("Failed to create directory: $directory");
          }
          
          // Save file with proper name
          $filename = $uploaded_file->getClientOriginalName();
          $destination = $directory . '/' . $filename;
          
          $file_uri = $this->fileSystem->saveUploadedFile(
            $uploaded_file, 
            $destination, 
            FileSystemInterface::EXISTS_RENAME
          );
          
          if (!$file_uri) {
            throw new \Exception("Failed to save uploaded file to $destination");
          }
          
          // Create file entity
          $file = File::create([
            'uri' => $file_uri,
            'uid' => $this->currentUser->id(),
            'status' => FILE_STATUS_PERMANENT,
            'filename' => $this->fileSystem->basename($file_uri),
          ]);
          $file->save();
          
          $attachment_fid = $file->id();
          
          $this->getLogger('tidy_feedback')->notice(
            "Test form: File uploaded successfully: @filename (FID: @fid)",
            ["@filename" => $file->getFilename(), "@fid" => $attachment_fid]
          );
        } else {
          throw new \Exception('File upload error: ' . $uploaded_file->getError());
        }
      }
      
      // Insert feedback entry
      $id = $this->database->insert('tidy_feedback')
        ->fields([
          'uuid' => \Drupal::service('uuid')->generate(),
          'uid' => $this->currentUser->id(),
          'created' => \Drupal::time()->getRequestTime(),
          'changed' => \Drupal::time()->getRequestTime(),
          'issue_type' => $data['issue_type'] ?? 'other',
          'severity' => $data['severity'] ?? 'normal',
          'description__value' => $data['description'],
          'description__format' => 'basic_html',
          'url' => $data['url'] ?? \Drupal::request()->getUri(),
          'element_selector' => $data['element_selector'] ?? '',
          'browser_info' => '{}',
          'status' => 'new',
          'attachment__target_id' => $attachment_fid,
        ])
        ->execute();
      
      // Record file usage
      if ($attachment_fid) {
        $this->fileUsage->add(
          File::load($attachment_fid),
          'tidy_feedback',
          'tidy_feedback',
          $id
        );
      }
      
      $this->getLogger('tidy_feedback')->notice(
        "Test form: Feedback #@id submitted successfully.",
        ["@id" => $id]
      );
      
      return new JsonResponse([
        'status' => 'success',
        'message' => 'Feedback submitted successfully (ID: ' . $id . ')',
        'details' => $attachment_fid ? "File attached with ID: $attachment_fid" : "No file attached",
        'id' => $id,
      ]);
    }
    catch (\Exception $e) {
      $this->getLogger('tidy_feedback')->error(
        "Test form error: @error\nTrace: @trace",
        [
          "@error" => $e->getMessage(),
          "@trace" => $e->getTraceAsString()
        ]
      );
      
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Error submitting feedback: ' . $e->getMessage(),
        'details' => substr($e->getTraceAsString(), 0, 1000),
      ], 500);
    }
  }
}