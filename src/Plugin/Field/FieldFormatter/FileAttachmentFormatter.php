<?php

namespace Drupal\tidy_feedback\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Plugin implementation of the 'file_attachment' formatter.
 *
 * @FieldFormatter(
 *   id = "file_attachment",
 *   label = @Translation("File Attachment"),
 *   field_types = {
 *     "uri",
 *     "string"
 *   }
 * )
 */
class FileAttachmentFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'link_text' => 'View Attachment',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->getSetting('link_text'),
      '#description' => $this->t('The text to display for the file link.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Link text: @text', ['@text' => $this->getSetting('link_text')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      if (!empty($item->value)) {
        $uri = $item->value;
        
        // Create the file URL.
        $url = file_create_url($uri);
        
        // Get filename from the URI.
        $filename = basename($uri);
        
        // Create a link to the file.
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $filename,
          '#url' => Url::fromUri($url),
          '#attributes' => [
            'class' => ['file-attachment-link'],
            'target' => '_blank',
            'download' => $filename,
          ],
          '#prefix' => '<div class="file-attachment">',
          '#suffix' => '</div>',
        ];
      }
    }

    return $elements;
  }

}