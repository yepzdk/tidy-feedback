<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Debug controller for testing file uploads.
 */
class TidyFeedbackDebugController extends ControllerBase implements ContainerInjectionInterface {

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
   * Constructs a new TidyFeedbackDebugController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    Connection $database,
    FileSystemInterface $file_system,
  ) {
    $this->database = $database;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_system')
    );
  }

  /**
   * Displays debug information.
   *
   * @return array
   *   A render array.
   */
  public function debugInfo() {
    // Check if user has permission.
    if (!$this->currentUser()->hasPermission('administer tidy feedback')) {
      return [
        '#markup' => $this->t('Access denied. You do not have permission to view this page.'),
      ];
    }

    // Get all feedback entries.
    $query = $this->database->select('tidy_feedback', 'tf')
      ->fields('tf')
      ->orderBy('id', 'DESC');
    $result = $query->execute()->fetchAll();

    // Build table.
    $rows = [];
    foreach ($result as $record) {
      $file_link = '';
      if (!empty($record->file_attachment)) {
        $file_url = file_create_url($record->file_attachment);
        $filename = basename($record->file_attachment);
        $file_link = '<a href="' . $file_url . '" target="_blank">' . $filename . '</a>';

        // Check if file exists.
        $file_exists = file_exists($this->fileSystem->realpath($record->file_attachment));
        $file_link .= ' (' . ($file_exists ? 'File exists' : 'File missing') . ')';
      }

      $rows[] = [
        'id' => $record->id,
        'created' => date('Y-m-d H:i:s', $record->created),
        'description' => substr($record->description__value, 0, 50) . (strlen($record->description__value) > 50 ? '...' : ''),
        'file_attachment' => !empty($record->file_attachment) ? $file_link : 'No file',
        'raw_path' => !empty($record->file_attachment) ? $record->file_attachment : '',
      ];
    }

    // Check folder permissions.
    $directory = 'public://tidy_feedback/attachments';
    $directory_info = '';
    if ($this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      $realpath = $this->fileSystem->realpath($directory);
      $is_writable = is_writable($realpath);
      $directory_info = $this->t('Directory: @dir (Realpath: @realpath, Writable: @writable)', [
        '@dir' => $directory,
        '@realpath' => $realpath,
        '@writable' => $is_writable ? 'Yes' : 'No',
      ]);
    }
    else {
      $directory_info = $this->t('Directory @dir does not exist or is not writable', ['@dir' => $directory]);
    }

    // Check PHP configuration.
    $build = [
      '#theme' => 'item_list',
      '#title' => $this->t('Debug Information'),
      '#items' => [
        $this->t('PHP Version: @version', ['@version' => phpversion()]),
        $this->t('Upload Max Filesize: @size', ['@size' => ini_get('upload_max_filesize')]),
        $this->t('Post Max Size: @size', ['@size' => ini_get('post_max_size')]),
        $this->t('File Uploads Enabled: @enabled', ['@enabled' => ini_get('file_uploads') ? 'Yes' : 'No']),
        $directory_info,
      ],
    ];

    // Add test upload form.
    $build['test_form'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Test File Upload'),
      'form' => [
        '#type' => 'inline_template',
        '#template' => '
          <form action="/tidy-feedback/debug/upload" method="post" enctype="multipart/form-data">
            <div class="form-item">
              <label for="test_file">Test File:</label>
              <input type="file" name="test_file" id="test_file">
            </div>
            <div class="form-actions">
              <input type="submit" value="Test Upload" class="button button--primary">
            </div>
          </form>
        ',
      ],
    ];

    // Add table of entries.
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        'id' => $this->t('ID'),
        'created' => $this->t('Created'),
        'description' => $this->t('Description'),
        'file_attachment' => $this->t('File Attachment'),
        'raw_path' => $this->t('Raw Path'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No feedback entries found.'),
    ];

    return $build;
  }

  /**
   * Handle test upload.
   */
  public function handleTestUpload() {
    // Check if user has permission.
    if (!$this->currentUser()->hasPermission('administer tidy feedback')) {
      return new Response('Access denied', 403);
    }

    $output = '<h1>File Upload Test Result</h1>';

    // Process uploaded file.
    $file = $_FILES['test_file'] ?? NULL;
    if ($file && $file['error'] == UPLOAD_ERR_OK) {
      $output .= '<h2>Upload Information</h2>';
      $output .= '<pre>' . print_r($file, TRUE) . '</pre>';

      // Attempt to save the file.
      $directory = 'public://tidy_feedback/attachments';
      if ($this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        $filename = basename($file['name']);
        $destination = $directory . '/' . $filename;

        $output .= '<h2>Saving File</h2>';
        $output .= "<p>Source: {$file['tmp_name']}</p>";
        $output .= "<p>Destination: $destination</p>";

        // Try method 1: move_uploaded_file.
        if (move_uploaded_file($file['tmp_name'], $this->fileSystem->realpath($destination))) {
          $output .= '<p style="color: green;">File uploaded successfully using move_uploaded_file()</p>';
        }
        else {
          $output .= '<p style="color: red;">Failed to upload file using move_uploaded_file()</p>';

          // Try method 2: file_save_data.
          $contents = file_get_contents($file['tmp_name']);
          if ($contents !== FALSE) {
            $uri = $this->fileSystem->saveData($contents, $destination, FileSystemInterface::EXISTS_REPLACE);
            if ($uri) {
              $output .= '<p style="color: green;">File uploaded successfully using saveData()</p>';
            }
            else {
              $output .= '<p style="color: red;">Failed to upload file using saveData()</p>';
            }
          }
          else {
            $output .= '<p style="color: red;">Failed to read file contents</p>';
          }
        }

        // Check if file exists at destination.
        if (file_exists($this->fileSystem->realpath($destination))) {
          $output .= '<p style="color: green;">File exists at destination</p>';
          $output .= '<p>File URL: <a href="' . file_create_url($destination) . '" target="_blank">' . file_create_url($destination) . '</a></p>';
        }
        else {
          $output .= '<p style="color: red;">File does not exist at destination</p>';
        }
      }
      else {
        $output .= '<p style="color: red;">Could not prepare directory: ' . $directory . '</p>';
      }
    }
    else {
      $error = $file ? $file['error'] : 'No file uploaded';
      $output .= '<p style="color: red;">Error: ' . $error . '</p>';
    }

    $output .= '<p><a href="/tidy-feedback/debug" class="button">Back to Debug Page</a></p>';

    return new Response($output);
  }

}
