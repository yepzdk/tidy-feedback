<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for admin feedback listing.
 */
class TidyFeedbackAdminController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new TidyFeedbackAdminController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * Lists feedback submissions.
   *
   * @return array
   *   A render array containing the feedback list.
   */
  public function listFeedback() {
    // Build the header.
    $header = [
      ['data' => $this->t('ID'), 'field' => 'id', 'sort' => 'desc'],
      ['data' => $this->t('Date'), 'field' => 'created'],
      ['data' => $this->t('Type'), 'field' => 'issue_type'],
      ['data' => $this->t('Severity'), 'field' => 'severity'],
      ['data' => $this->t('Description')],
      ['data' => $this->t('URL')],
      ['data' => $this->t('Status'), 'field' => 'status'],
      ['data' => $this->t('Attachment')],
      ['data' => $this->t('Operations')],
    ];

    // Get query parameters.
    $query = \Drupal::request()->query;
    
    // Table sort.
    $order = $query->get('order', 'DESC');
    $sort = $query->get('sort', 'created');
    
    // Pagination.
    $page = $query->get('page', 0);
    $limit = 20;
    $offset = $page * $limit;

    // Build the query.
    $query = $this->database->select('tidy_feedback', 'tf')
      ->fields('tf', [
        'id',
        'created',
        'issue_type',
        'severity',
        'description__value',
        'url',
        'status',
        'attachment__target_id',
      ])
      ->orderBy($sort, $order)
      ->range($offset, $limit);

    // Execute the query.
    $result = $query->execute();

    // Build the rows.
    $rows = [];
    foreach ($result as $record) {
      // Format the description.
      $description = substr(strip_tags($record->description__value), 0, 100);
      if (strlen($record->description__value) > 100) {
        $description .= '...';
      }

      // Format the URL.
      $url = substr($record->url, 0, 50);
      if (strlen($record->url) > 50) {
        $url .= '...';
      }
      $url_link = Link::fromTextAndUrl(
        $url,
        Url::fromUri($record->url, ['attributes' => ['target' => '_blank']])
      )->toString();

      // Format the date.
      $date = $this->dateFormatter->format($record->created, 'short');

      // Check for attachment.
      $attachment = $record->attachment__target_id ? $this->t('Yes') : $this->t('No');

      // Build operations.
      $view_url = Url::fromRoute(
        'entity.tidy_feedback.canonical',
        ['tidy_feedback' => $record->id]
      );
      $edit_url = Url::fromRoute(
        'entity.tidy_feedback.edit_form',
        ['tidy_feedback' => $record->id]
      );
      $delete_url = Url::fromRoute(
        'entity.tidy_feedback.delete_form',
        ['tidy_feedback' => $record->id]
      );

      $operations = [
        '#type' => 'operations',
        '#links' => [
          'view' => [
            'title' => $this->t('View'),
            'url' => $view_url,
          ],
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => $edit_url,
          ],
          'delete' => [
            'title' => $this->t('Delete'),
            'url' => $delete_url,
          ],
        ],
      ];

      // Add the row.
      $rows[] = [
        'data' => [
          $record->id,
          $date,
          $record->issue_type,
          $record->severity,
          $description,
          ['data' => ['#markup' => $url_link]],
          $record->status,
          $attachment,
          ['data' => $operations],
        ],
      ];
    }

    // Build the table.
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No feedback submissions found.'),
      '#attributes' => [
        'class' => ['tidy-feedback-listing'],
      ],
    ];

    // Add pager.
    $build['pager'] = [
      '#type' => 'pager',
      '#quantity' => 5,
    ];

    // Add CSS.
    $build['#attached']['library'][] = 'tidy_feedback/tidy_feedback_admin';

    return $build;
  }

  /**
   * Gets the title for the feedback detail page.
   *
   * @param int $tidy_feedback
   *   The feedback ID.
   *
   * @return string
   *   The page title.
   */
  public function getTitle($tidy_feedback) {
    return $this->t('Feedback #@id', ['@id' => $tidy_feedback]);
  }

}