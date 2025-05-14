<?php

namespace Drupal\tidy_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the tidy feedback form.
 */
class TidyFeedbackFormController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new TidyFeedbackFormController.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Returns the feedback form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array|Response
   *   A render array or error response.
   */
  public function displayForm(Request $request) {
    // Enable detailed error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
    
    try {
      $element_selector = $request->query->get('element_selector', '');
      $url = $request->query->get('url', '');
      
      // Log the request parameters for debugging
      $this->getLogger('tidy_feedback')->notice('Form page accessed with selector: @selector, url: @url', [
        '@selector' => $element_selector,
        '@url' => $url,
      ]);
  
      // Log server and request info for debugging
      $this->getLogger('tidy_feedback')->notice('Request method: @method, content-type: @content', [
        '@method' => $request->getMethod(),
        '@content' => $request->headers->get('Content-Type'),
      ]);
  
      // Create the page render array.
      $build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['tidy-feedback-form-container'],
        ],
      ];
  
      // Add page styling.
      $build['#attached']['library'][] = 'tidy_feedback/tidy_feedback';
  
      // Build and add the form.
      $build['form'] = $this->formBuilder->getForm('Drupal\tidy_feedback\Form\TidyFeedbackForm');
  
      // Add page styling.
      $build['#attached']['library'][] = 'tidy_feedback/tidy_feedback_form_page';
  
      return $build;
    }
    catch (\Exception $e) {
      // Log the error
      $this->getLogger('tidy_feedback')->error('Error displaying form: @error, trace: @trace', [
        '@error' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
      ]);
      
      // Return error response with debugging information
      $output = '<h1>Error</h1>';
      $output .= '<p>An error occurred while displaying the form:</p>';
      $output .= '<pre>' . $e->getMessage() . '</pre>';
      $output .= '<p>Stack trace:</p>';
      $output .= '<pre>' . $e->getTraceAsString() . '</pre>';
      
      // Add server and request information
      $output .= '<h2>Request Information</h2>';
      $output .= '<ul>';
      $output .= '<li>Method: ' . $request->getMethod() . '</li>';
      $output .= '<li>Content-Type: ' . $request->headers->get('Content-Type') . '</li>';
      $output .= '<li>Query parameters: ' . print_r($request->query->all(), TRUE) . '</li>';
      $output .= '</ul>';
      
      return new Response($output, 500);
    }
  }

}