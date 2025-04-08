<?php

namespace Drupal\tidy_feedback\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Form controller for Feedback edit forms.
 */
class FeedbackEditForm extends ContentEntityForm
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        /** @var \Drupal\tidy_feedback\Entity\Feedback $entity */
        $form = parent::buildForm($form, $form_state);

        // Add a more descriptive title for the admin form
        $form["#title"] = $this->t("Edit Feedback #@id", [
            "@id" => $this->entity->id(),
        ]);

        // Make some fields read-only in edit form
        if (isset($form["created"])) {
            $form["created"]["#disabled"] = true;
        }

        if (isset($form["uid"])) {
            $form["uid"]["#disabled"] = true;
        }

        if (isset($form["url"])) {
            $form["url"]["#disabled"] = true;
        }

        if (isset($form["element_selector"])) {
            $form["element_selector"]["#disabled"] = true;
        }

        if (isset($form["browser_info"])) {
            $form["browser_info"]["#disabled"] = true;
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state)
    {
        $entity = $this->entity;
        $status = parent::save($form, $form_state);

        switch ($status) {
            case SAVED_NEW:
                $this->messenger()->addStatus(
                    $this->t("Feedback #%id has been created.", [
                        "%id" => $entity->id(),
                    ])
                );
                break;

            default:
                $this->messenger()->addStatus(
                    $this->t("Feedback #%id has been updated.", [
                        "%id" => $entity->id(),
                    ])
                );
        }

        $form_state->setRedirect("entity.tidy_feedback.collection");
    }
}
