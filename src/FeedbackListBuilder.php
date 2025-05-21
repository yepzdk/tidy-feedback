<?php

namespace Drupal\tidy_feedback;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Feedback entities.
 */
class FeedbackListBuilder extends EntityListBuilder {
  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Constructs a new FeedbackListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter,
    RedirectDestinationInterface $redirect_destination,
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ) {
    return new static(
          $entity_type,
          $container
            ->get('entity_type.manager')
            ->getStorage($entity_type->id()),
          $container->get('date.formatter'),
          $container->get('redirect.destination')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'id' => $this->t('ID'),
      'created' => $this->t('Created'),
      'issue_type' => $this->t('Issue Type'),
      'status' => $this->t('Status'),
      'url' => $this->t('URL'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\tidy_feedback\Entity\Feedback $entity */
    $row['id'] = $entity->id();
    $row['created'] = $this->dateFormatter->format(
          $entity->getCreatedTime(),
          'short'
      );
    $row['issue_type'] = $entity->getIssueType();
    $row['status'] = $entity->getStatus();

    $url_value = $entity->getUrl();
    $url = $url_value
            ? Url::fromUri($url_value, ['attributes' => ['target' => '_blank']])
            : NULL;
    $row['url'] = $url
            ? [
              'data' => [
                '#type' => 'link',
                '#title' => $this->t('View page'),
                '#url' => $url,
              ],
            ]
            : '';

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }

    return $operations;
  }

}
