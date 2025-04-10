<?php

namespace Drupal\tidy_feedback\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Tidy Feedback.
 */
class TidyFeedbackSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "tidy_feedback_settings_form";
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ["tidy_feedback.settings"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config("tidy_feedback.settings");

    $form["appearance"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Appearance Settings"),
    ];

    $form["appearance"]["banner_position"] = [
      "#type" => "select",
      "#title" => $this->t("Banner Position"),
      "#options" => [
        "right" => $this->t("Right side"),
        "left" => $this->t("Left side"),
      ],
      "#default_value" => $config->get("banner_position") ?: "right",
      "#description" => $this->t(
        "Position of the feedback banner on the screen."
      ),
    ];

    $form["appearance"]["highlight_color"] = [
      "#type" => "color",
      "#title" => $this->t("Highlight Color"),
      "#default_value" => $config->get("highlight_color") ?: "#ff0000",
      "#description" => $this->t(
        "Color for highlighting elements on the page."
      ),
    ];

    $form["behavior"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Behavior Settings"),
    ];

    $form["behavior"]["enable_screenshots"] = [
      "#type" => "checkbox",
      "#title" => $this->t("Enable screenshots"),
      "#default_value" => $config->get("enable_screenshots") ?: FALSE,
      "#description" => $this->t(
        "Allow users to include screenshots with their feedback."
      ),
    ];

    $form["email"] = [
      "#type" => "fieldset",
      "#title" => $this->t("Notification Settings"),
    ];

    $form["email"]["notify_email"] = [
      "#type" => "email",
      "#title" => $this->t("Notification Email"),
      "#default_value" => $config->get("notify_email") ?: "",
      "#description" => $this->t(
        "Email address to notify when new feedback is submitted. Leave blank to disable notifications."
      ),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config("tidy_feedback.settings")
      ->set("banner_position", $form_state->getValue("banner_position"))
      ->set("highlight_color", $form_state->getValue("highlight_color"))
      ->set(
        "enable_screenshots",
        $form_state->getValue("enable_screenshots")
      )
      ->set("notify_email", $form_state->getValue("notify_email"))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
