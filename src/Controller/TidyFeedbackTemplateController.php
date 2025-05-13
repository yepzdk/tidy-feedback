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
   * Redirects to the simple form controller.
   *
   * @param string $element_selector
   *   The CSS selector of the element to provide feedback on.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the simple form.
   */
  public function displayForm($element_selector = '') {
    // Redirect to the simple form controller for consistency
    return new \Symfony\Component\HttpFoundation\RedirectResponse(
      \Drupal::url('tidy_feedback.simple_test', ['element_selector' => $element_selector])
    );
  }

  /**
   * Redirects to the simple form submission handler.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the simple form handler.
   */
  public function submitForm(Request $request) {
    // Log the redirect for debugging
    $this->getLogger('tidy_feedback')->notice(
      "Redirecting template form submission to simple form handler"
    );
    
    // Redirect to the simple form controller for consistency
    return $this->forward('Drupal\tidy_feedback\Controller\TidyFeedbackSimpleController::handleSubmit', [], $request->request->all());
  }

}