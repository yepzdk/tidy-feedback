<?php

namespace Drupal\tidy_feedback\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Feedback edit forms.
 */
class FeedbackEditForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\tidy_feedback\Entity\Feedback $entity */
    $form = parent::buildForm($form, $form_state);

    // Add a more descriptive title for the admin form.
    $form['#title'] = $this->t('Edit Feedback #@id', [
      '@id' => $this->entity->id(),
    ]);

    // Make some fields read-only in edit form.
    if (isset($form['created'])) {
      $form['created']['#disabled'] = TRUE;
    }

    if (isset($form['uid'])) {
      $form['uid']['#disabled'] = TRUE;
    }

    if (isset($form['url'])) {
      $form['url']['#disabled'] = TRUE;
    }

    if (isset($form['element_selector'])) {
      $form['element_selector']['#disabled'] = TRUE;
    }

    if (isset($form['browser_info'])) {
      $form['browser_info']['#disabled'] = TRUE;
    }

    // Add image preview for attachment if it's an image.
    $this->addFileAttachmentPreview($form);

    return $form;
  }

  /**
   * Adds a preview for file attachments.
   *
   * @param array $form
   *   The form array to modify.
   */
  protected function addFileAttachmentPreview(array &$form) {
    /** @var \Drupal\tidy_feedback\Entity\Feedback $entity */
    $entity = $this->entity;
    $file_uri = $entity->getFileAttachment();

    if (!empty($file_uri)) {
      // Generate a URL for the file.
      $url = \Drupal::service('file_url_generator')->generateAbsoluteString($file_uri);
      $filename = basename($file_uri);

      // Get the file extension.
      $extension = pathinfo($file_uri, PATHINFO_EXTENSION);
      $image_extensions = ['jpg', 'jpeg', 'png', 'gif'];

      // Check if this is an image file.
      if (in_array(strtolower($extension), $image_extensions)) {
        // Add an image preview with link.
        if (isset($form['file_attachment'])) {
          $form['file_attachment']['#prefix'] = '<div class="attachment-preview">' .
                        '<p>' . $this->t('Current image attachment: <a href="@url" target="_blank">@filename</a> (opens in new tab)',
                    ['@url' => $url, '@filename' => $filename]) . '</p>' .
                        '</div>';
        }
      }
      else {
        // For non-image files, just add a download link.
        if (isset($form['file_attachment'])) {
          $form['file_attachment']['#prefix'] = '<div class="attachment-preview">' .
                        '<p>' . $this->t('Current file attachment: <a href="@url" target="_blank">@filename</a> (opens in new tab)',
                    ['@url' => $url, '@filename' => $filename]) . '</p>' .
                        '</div>';
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus(
              $this->t('Feedback #%id has been created.', [
                '%id' => $entity->id(),
              ])
          );
        break;

      default:
        $this->messenger()->addStatus(
              $this->t('Feedback #%id has been updated.', [
                '%id' => $entity->id(),
              ])
          );
    }

    $form_state->setRedirect('entity.tidy_feedback.collection');
  }

}
