<?php

namespace Drupal\tidy_feedback\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting Feedback entities.
 */
class FeedbackDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete feedback #%id?', [
      '%id' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.tidy_feedback.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    return $this->getCancelUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\tidy_feedback\Entity\Feedback $entity */
    $entity = $this->getEntity();
    $entity->delete();

    $this->messenger()->addStatus($this->getDeletionMessage());
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    /** @var \Drupal\tidy_feedback\Entity\Feedback $entity */
    $entity = $this->getEntity();
    return $this->t('Feedback #@id has been deleted.', [
      '@id' => $entity->id(),
    ]);
  }

}
