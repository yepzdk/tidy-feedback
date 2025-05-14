<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a controller for the feedback form page.
 */
class TidyFeedbackFormPageController extends ControllerBase {

  /**
   * Returns the feedback form page.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object with the feedback form.
   */
  public function displayForm() {
    // Handle form submission
    $request = \Drupal::request();
    if ($request->isMethod('POST')) {
      return $this->handleFormSubmission($request);
    }
    
    try {
      // Get parameters
      $element_selector = $request->query->get('element_selector', '');
      $url = $request->query->get('url', '');
      

      
      // Create a styled form
      $output = '<!DOCTYPE html><html lang="en"><head><title>Submit Feedback</title>';
      $output .= '<meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        :root {
          --primary-color: #0071b8;
          --primary-hover: #00539f;
          --light-bg: #f7f9fc;
          --border-color: #ddd;
          --success-color: #43a047;
        }
        body {
          font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
          line-height: 1.6;
          max-width: 800px;
          margin: 0 auto;
          padding: 2rem;
          color: #333;
          background-color: #fff;
        }
        .tidy-feedback-page {
          border-radius: 8px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
          padding: 2rem;
          background-color: #fff;
        }
        .tidy-feedback-page__header {
          margin-bottom: 1.5rem;
          padding-bottom: 1rem;
          border-bottom: 1px solid var(--border-color);
        }
        h1 {
          margin-top: 0;
          color: var(--primary-color);
          font-weight: 600;
        }
        .element-info {
          background-color: var(--light-bg);
          padding: 1rem;
          margin: 1rem 0;
          border-radius: 4px;
          border-left: 4px solid var(--primary-color);
          word-break: break-word;
        }
        .form-item {
          margin-bottom: 1.5rem;
        }
        label {
          display: block;
          font-weight: 600;
          margin-bottom: 0.5rem;
        }
        select, textarea {
          width: 100%;
          padding: 0.75rem;
          border-radius: 4px;
          border: 1px solid var(--border-color);
          font-size: 1rem;
        }
        textarea {
          min-height: 150px;
          resize: vertical;
        }
        .description {
          font-size: 0.875rem;
          color: #666;
          margin-top: 0.5rem;
        }
        button, .button {
          display: inline-block;
          background-color: var(--primary-color);
          color: white;
          border: none;
          padding: 0.75rem 1.5rem;
          font-size: 1rem;
          border-radius: 4px;
          cursor: pointer;
          text-decoration: none;
          font-weight: 500;
          transition: background-color 0.2s;
        }
        button:hover, .button:hover {
          background-color: var(--primary-hover);
        }
        .button--secondary {
          background-color: transparent;
          color: var(--primary-color);
          border: 1px solid var(--primary-color);
          margin-left: 0.5rem;
        }
        .button--secondary:hover {
          background-color: var(--light-bg);
        }
        .form-actions {
          margin-top: 2rem;
          display: flex;
          gap: 1rem;
        }
        @media (max-width: 576px) {
          body {
            padding: 1rem;
          }
          .tidy-feedback-page {
            padding: 1rem;
            box-shadow: none;
          }
          .form-actions {
            flex-direction: column;
          }
          .button--secondary {
            margin-left: 0;
            margin-top: 0.5rem;
          }
        }
      </style>';
      
      // Add javascript to set browser info
      $output .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
          try {
            var browserInfo = {
              userAgent: navigator.userAgent,
              screenWidth: window.screen.width,
              screenHeight: window.screen.height,
              viewportWidth: window.innerWidth,
              viewportHeight: window.innerHeight,
              devicePixelRatio: window.devicePixelRatio || 1,
              platform: navigator.platform,
              language: navigator.language,
              timestamp: new Date().toISOString()
            };
            document.getElementById("browser-info").value = JSON.stringify(browserInfo);
          } catch (error) {
            console.error("Error setting browser info:", error);
          }
        });
      </script></head><body>
      <div class="tidy-feedback-page">
        <div class="tidy-feedback-page__header">
          <h1>Submit Feedback</h1>';
      
      if (!empty($element_selector)) {
        $output .= '<div class="element-info">';
        $output .= '<strong>Selected element:</strong> ' . htmlspecialchars($element_selector);
        $output .= '</div>';
      }
      
      if (!empty($url)) {
        $output .= '<p><strong>URL:</strong> <a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a></p>';
      }
      
      $output .= '</div>'; // Close header
      
      // Use the absolute URL to prevent path issues
      $site_base = $request->getSchemeAndHttpHost();
      $form_path = '/tidy-feedback/feedback-form';
      
      $output .= '<form method="post" action="' . $site_base . $form_path . '" enctype="multipart/form-data">';
      
      $output .= '<div class="form-item">';
      $output .= '<label for="issue_type">Issue Type</label>';
      $output .= '<select id="issue_type" name="issue_type">';
      $output .= '<option value="bug">Bug</option>';
      $output .= '<option value="enhancement">Enhancement</option>';
      $output .= '<option value="question">Question</option>';
      $output .= '<option value="other" selected>Other</option>';
      $output .= '</select>';
      $output .= '</div>';
      
      $output .= '<div class="form-item">';
      $output .= '<label for="severity">Severity</label>';
      $output .= '<select id="severity" name="severity">';
      $output .= '<option value="critical">Critical</option>';
      $output .= '<option value="high">High</option>';
      $output .= '<option value="normal" selected>Normal</option>';
      $output .= '<option value="low">Low</option>';
      $output .= '</select>';
      $output .= '</div>';
      
      $output .= '<div class="form-item">';
      $output .= '<label for="description">Description</label>';
      $output .= '<textarea id="description" name="description" required></textarea>';
      $output .= '<div class="description">Please describe the issue or suggestion in detail.</div>';
      $output .= '</div>';
      
      $output .= '<div class="form-item">';
      $output .= '<label for="attachment">Attachment</label>';
      $output .= '<input type="file" id="attachment" name="files[attachment]" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">';
      $output .= '<div class="description">Upload a file to provide additional context (optional).</div>';
      $output .= '</div>';
      
      $output .= '<input type="hidden" name="element_selector" value="' . htmlspecialchars($element_selector) . '">';
      $output .= '<input type="hidden" name="url" value="' . htmlspecialchars($url) . '">';
      $output .= '<input type="hidden" id="browser-info" name="browser_info" value="{}">';
      
      $output .= '<div class="form-actions">';
      $output .= '<button type="submit" class="button">Submit Feedback</button>';
      
      if (!empty($url)) {
        $output .= '<a href="' . htmlspecialchars($url) . '" class="button button--secondary">Cancel</a>';
      } else {
        $output .= '<a href="/" class="button button--secondary">Cancel</a>';
      }
      
      $output .= '</div>'; // Close form-actions
      $output .= '</form>';
      $output .= '</div>'; // Close tidy-feedback-page
      $output .= '</body></html>';
      
      return new Response($output);
    } catch (\Exception $e) {
      \Drupal::logger('tidy_feedback')->error('Error displaying form: @message', [
        '@message' => $e->getMessage()
      ]);
      return new Response('<html><body><h1>Error</h1><p>' . $e->getMessage() . '</p><pre>' . $e->getTraceAsString() . '</pre></body></html>', 500);
    }
  }

  /**
   * Handles form submission.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object.
   */
  protected function handleFormSubmission(Request $request) {
    try {

      
      // Extract form data
      $data = $request->request->all();
      $files = $request->files->all();
      

      
      $element_selector = isset($data['element_selector']) ? $data['element_selector'] : '';
      $url = isset($data['url']) ? $data['url'] : '';
      $description = isset($data['description']) ? $data['description'] : '';
      
      // Validate
      if (empty($description)) {

        return new Response('<html><body><h1>Error</h1><p>Description is required.</p><p><a href="javascript:history.back()">Go back</a></p></body></html>');
      }
      
      // Log the submission
      \Drupal::logger('tidy_feedback')->notice('Feedback submitted: @desc for element @elem', [
        '@desc' => substr($description, 0, 100),
        '@elem' => $element_selector,
      ]);
      
      // Process file attachment if present
      $attachment_fid = NULL;
      if (!empty($files['files']['attachment'])) {
        $uploaded_file = $files['files']['attachment'];
        
        try {
          if ($uploaded_file->isValid()) {
            // Prepare directory
            $directory = 'public://tidy_feedback/attachments';
            $file_system = \Drupal::service('file_system');
            
            if (!$file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS)) {
              throw new \Exception("Failed to create directory: $directory");
            }
            
            // Save file
            $filename = $uploaded_file->getClientOriginalName();
            $destination = $directory . '/' . $filename;
            
            $file_uri = $file_system->saveUploadedFile(
              $uploaded_file, 
              $destination,
              \Drupal\Core\File\FileSystemInterface::EXISTS_RENAME
            );
            
            if (!$file_uri) {
              throw new \Exception("Failed to save uploaded file");
            }
            
            // Create file entity
            $file = \Drupal\file\Entity\File::create([
              'uri' => $file_uri,
              'uid' => \Drupal::currentUser()->id(),
              'status' => FILE_STATUS_PERMANENT,
              'filename' => $file_system->basename($file_uri),
            ]);
            $file->save();
            
            $attachment_fid = $file->id();
          }
        } catch (\Exception $e) {
          \Drupal::logger('tidy_feedback')->error('Error processing attachment: @error', [
            '@error' => $e->getMessage()
          ]);
          // Continue without the file
        }
      }
      
      // Save to database
      $database = \Drupal::database();
      $time = \Drupal::time()->getRequestTime();
      $uuid = \Drupal::service('uuid')->generate();
      $current_user = \Drupal::currentUser();
      

      
      $id = $database->insert('tidy_feedback')
        ->fields([
          'uuid' => $uuid,
          'uid' => $current_user->id(),
          'created' => $time,
          'changed' => $time,
          'issue_type' => isset($data['issue_type']) ? $data['issue_type'] : 'other',
          'severity' => isset($data['severity']) ? $data['severity'] : 'normal',
          'description__value' => $description,
          'description__format' => 'basic_html',
          'url' => $url,
          'element_selector' => $element_selector,
          'browser_info' => isset($data['browser_info']) ? $data['browser_info'] : '{}',
          'status' => 'new',
          'attachment__target_id' => $attachment_fid,
        ])
        ->execute();
      
      // Register file usage if attachment was uploaded
      if ($attachment_fid) {
        \Drupal::service('file.usage')->add(
          \Drupal\file\Entity\File::load($attachment_fid),
          'tidy_feedback',
          'tidy_feedback',
          $id
        );
      }
      

      
      // Generate a success page
      $output = '<!DOCTYPE html><html lang="en"><head><title>Feedback Submitted</title>';
      $output .= '<meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        :root {
          --primary-color: #0071b8;
          --primary-hover: #00539f;
          --success-color: #43a047;
          --success-bg: #e8f5e9;
          --border-color: #ddd;
          --light-bg: #f7f9fc;
        }
        body {
          font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
          line-height: 1.6;
          max-width: 800px;
          margin: 0 auto;
          padding: 2rem;
          color: #333;
          background-color: #fff;
        }
        .tidy-feedback-success {
          border-radius: 8px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
          padding: 2rem;
          background-color: #fff;
          text-align: center;
        }
        h1 {
          margin-top: 0;
          color: var(--success-color);
          font-weight: 600;
        }
        .success-message {
          background-color: var(--success-bg);
          border-left: 4px solid var(--success-color);
          padding: 1.5rem;
          margin: 1.5rem 0;
          border-radius: 4px;
          text-align: left;
        }
        .button {
          display: inline-block;
          background-color: var(--primary-color);
          color: white;
          border: none;
          padding: 0.75rem 1.5rem;
          font-size: 1rem;
          border-radius: 4px;
          cursor: pointer;
          text-decoration: none;
          font-weight: 500;
          transition: background-color 0.2s;
          margin-top: 1rem;
        }
        .button:hover {
          background-color: var(--primary-hover);
        }
        @media (max-width: 576px) {
          body {
            padding: 1rem;
          }
          .tidy-feedback-success {
            padding: 1.5rem;
            box-shadow: none;
          }
        }
      </style></head><body>
      <div class="tidy-feedback-success">
        <h1>Feedback Submitted</h1>
        <div class="success-message">
          <p><strong>Success!</strong> Your feedback has been submitted successfully.</p>
        </div>
        
        <p>Thank you for your feedback. It has been recorded in our system and will be reviewed by our team.</p>';
      
      if (!empty($url)) {
        $output .= '<a href="' . htmlspecialchars($url) . '" class="button">Return to previous page</a>';
      } else {
        $output .= '<a href="/" class="button">Return to homepage</a>';
      }
      
      $output .= '</div></body></html>';
      
      return new Response($output);
    } catch (\Exception $e) {
      \Drupal::logger('tidy_feedback')->error('Error submitting feedback: @message', [
        '@message' => $e->getMessage()
      ]);
      
      return new Response('<!DOCTYPE html><html lang="en"><head><title>Error</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
          body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            color: #333;
          }
          .error-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            text-align: center;
          }
          h1 {
            color: #d32f2f;
            margin-top: 0;
          }
          .error-message {
            background-color: #ffebee;
            border-left: 4px solid #d32f2f;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            text-align: left;
          }
          .button {
            display: inline-block;
            background-color: #0071b8;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 1.5rem;
          }
          .button:hover {
            background-color: #00539f;
          }
        </style>
      </head><body>
        <div class="error-container">
          <h1>Error</h1>
          <div class="error-message">
            <p>An error occurred while processing your feedback.</p>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
          </div>
          <a href="javascript:history.back()" class="button">Go back and try again</a>
        </div>
      </body></html>', 500);
    }
  }

}