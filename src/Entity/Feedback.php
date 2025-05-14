<?php

namespace Drupal\tidy_feedback\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\user\UserInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the Feedback entity.
 *
 * @ContentEntityType(
 *   id = "tidy_feedback",
 *   label = @Translation("Tidy Feedback"),
 *   label_collection = @Translation("Tidy Feedback"),
 *   label_singular = @Translation("feedback item"),
 *   label_plural = @Translation("feedback items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count feedback item",
 *     plural = "@count feedback items",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\tidy_feedback\FeedbackAccessControlHandler",
 *     "list_builder" = "Drupal\tidy_feedback\FeedbackListBuilder",
 *     "form" = {
 *       "default" = "Drupal\tidy_feedback\Form\FeedbackForm",
 *       "add" = "Drupal\tidy_feedback\Form\FeedbackForm",
 *       "edit" = "Drupal\tidy_feedback\Form\FeedbackEditForm",
 *       "delete" = "Drupal\tidy_feedback\Form\FeedbackDeleteForm",
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "tidy_feedback",
 *   admin_permission = "administer tidy feedback",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "created" = "created",
 *   },
 *   links = {
 *     "canonical" = "/admin/reports/tidy-feedback/{tidy_feedback}",
 *     "edit-form" = "/admin/reports/tidy-feedback/{tidy_feedback}/edit",
 *     "delete-form" = "/admin/reports/tidy-feedback/{tidy_feedback}/delete",
 *     "collection" = "/admin/reports/tidy-feedback",
 *   },
 * )
 */
class Feedback extends ContentEntityBase implements ContentEntityInterface
{
    use EntityChangedTrait;

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(
        EntityTypeInterface $entity_type
    ) {
        $fields = parent::baseFieldDefinitions($entity_type);

        $fields["id"] = BaseFieldDefinition::create("integer")
            ->setLabel(t("ID"))
            ->setDescription(t("The ID of the Feedback entity."))
            ->setReadOnly(true);

        $fields["uuid"] = BaseFieldDefinition::create("uuid")
            ->setLabel(t("UUID"))
            ->setDescription(t("The UUID of the Feedback entity."))
            ->setReadOnly(true);

        $fields["uid"] = BaseFieldDefinition::create("entity_reference")
            ->setLabel(t("Submitted by"))
            ->setDescription(t("The user who submitted the feedback."))
            ->setSetting("target_type", "user")
            ->setSetting("handler", "default")
            ->setDefaultValueCallback(
                'Drupal\tidy_feedback\Entity\Feedback::getCurrentUserId'
            )
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "author",
                "weight" => 0,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["created"] = BaseFieldDefinition::create("created")
            ->setLabel(t("Created"))
            ->setDescription(t("The time that the feedback was created."));

        $fields["changed"] = BaseFieldDefinition::create("changed")
            ->setLabel(t("Changed"))
            ->setDescription(t("The time that the feedback was last edited."));

        $fields["issue_type"] = BaseFieldDefinition::create("string")
            ->setLabel(t("Issue Type"))
            ->setDescription(t("The type of issue being reported."))
            ->setSettings([
                "allowed_values" => [
                    "bug" => t("Bug"),
                    "enhancement" => t("Enhancement"),
                    "question" => t("Question"),
                    "other" => t("Other"),
                ],
                "max_length" => 64,
            ])
            ->setRequired(true)
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "string",
                "weight" => 1,
            ])
            ->setDisplayOptions("form", [
                "type" => "options_select",
                "weight" => 1,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["severity"] = BaseFieldDefinition::create("string")
            ->setLabel(t("Severity"))
            ->setDescription(t("The severity of the issue."))
            ->setSettings([
                "allowed_values" => [
                    "critical" => t("Critical"),
                    "high" => t("High"),
                    "normal" => t("Normal"),
                    "low" => t("Low"),
                ],
                "max_length" => 64,
            ])
            ->setDefaultValue("normal")
            ->setRequired(true)
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "string",
                "weight" => 2,
            ])
            ->setDisplayOptions("form", [
                "type" => "options_select",
                "weight" => 2,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        // Note the field name change from 'description' to 'description__value'
        $fields["description"] = BaseFieldDefinition::create("text_long")
            ->setLabel(t("Description"))
            ->setDescription(t("A description of the feedback."))
            ->setRequired(true)
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "text_default",
                "weight" => 3,
            ])
            ->setDisplayOptions("form", [
                "type" => "text_textarea",
                "weight" => 3,
                "rows" => 5,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["url"] = BaseFieldDefinition::create("uri")
            ->setLabel(t("URL"))
            ->setDescription(t("The URL where the feedback was submitted."))
            ->setRequired(true)
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "uri_link",
                "weight" => 4,
            ])
            ->setDisplayOptions("form", [
                "type" => "uri",
                "weight" => 4,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["element_selector"] = BaseFieldDefinition::create("string")
            ->setLabel(t("Element Selector"))
            ->setDescription(
                t("The CSS selector of the element that was selected.")
            )
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "string",
                "weight" => 5,
            ])
            ->setDisplayOptions("form", [
                "type" => "string_textfield",
                "weight" => 5,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["browser_info"] = BaseFieldDefinition::create("string_long")
            ->setLabel(t("Browser Information"))
            ->setDescription(
                t("Information about the browser and device used.")
            )
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "string",
                "weight" => 6,
            ])
            ->setDisplayOptions("form", [
                "type" => "string_textarea",
                "weight" => 6,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["status"] = BaseFieldDefinition::create("string")
            ->setLabel(t("Status"))
            ->setDescription(t("The status of the feedback."))
            ->setSettings([
                "allowed_values" => [
                    "new" => t("New"),
                    "in_progress" => t("In Progress"),
                    "resolved" => t("Resolved"),
                    "closed" => t("Closed"),
                ],
                "max_length" => 64,
            ])
            ->setDefaultValue("new")
            ->setRequired(true)
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "string",
                "weight" => 7,
            ])
            ->setDisplayOptions("form", [
                "type" => "options_select",
                "weight" => 7,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        $fields["admin_comments"] = BaseFieldDefinition::create("text_long")
            ->setLabel(t("Administrative Comments"))
            ->setDescription(
                t("Comments from administrators about this feedback.")
            )
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "text_default",
                "weight" => 8,
            ])
            ->setDisplayOptions("form", [
                "type" => "text_textarea",
                "weight" => 8,
                "rows" => 3,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);
            
        $fields["file_attachment"] = BaseFieldDefinition::create("uri")
            ->setLabel(t("File Attachment"))
            ->setDescription(t("A file uploaded with the feedback."))
            ->setDisplayOptions("view", [
                "label" => "above",
                "type" => "file_link",
                "weight" => 9,
            ])
            ->setDisplayOptions("form", [
                "type" => "file",
                "weight" => 9,
            ])
            ->setDisplayConfigurable("form", true)
            ->setDisplayConfigurable("view", true);

        return $fields;
    }

    /**
     * Default value callback for 'uid' base field definition.
     *
     * @return array
     *   An array of default values.
     */
    public static function getCurrentUserId()
    {
        return [\Drupal::currentUser()->id()];
    }

    /**
     * Gets the user who submitted the feedback.
     *
     * @return \Drupal\user\UserInterface
     *   The user entity.
     */
    public function getOwner()
    {
        return $this->get("uid")->entity;
    }

    /**
     * Sets the user who submitted the feedback.
     *
     * @param \Drupal\user\UserInterface $account
     *   The user entity.
     *
     * @return $this
     */
    public function setOwner(UserInterface $account)
    {
        $this->set("uid", $account->id());
        return $this;
    }

    /**
     * Gets the user ID of the submitter.
     *
     * @return int|null
     *   The user ID of the submitter, or NULL if there is no submitter.
     */
    public function getOwnerId()
    {
        return $this->get("uid")->target_id;
    }

    /**
     * Sets the user ID of the submitter.
     *
     * @param int $uid
     *   The user ID of the submitter.
     *
     * @return $this
     */
    public function setOwnerId($uid)
    {
        $this->set("uid", $uid);
        return $this;
    }

    /**
     * Gets the creation time.
     *
     * @return int
     *   The creation timestamp.
     */
    public function getCreatedTime()
    {
        return $this->get("created")->value;
    }

    /**
     * Sets the creation time.
     *
     * @param int $timestamp
     *   The creation timestamp.
     *
     * @return $this
     */
    public function setCreatedTime($timestamp)
    {
        $this->set("created", $timestamp);
        return $this;
    }

    /**
     * Gets the issue type.
     *
     * @return string
     *   The issue type.
     */
    public function getIssueType()
    {
        return $this->get("issue_type")->value;
    }

    /**
     * Sets the issue type.
     *
     * @param string $issue_type
     *   The issue type.
     *
     * @return $this
     */
    public function setIssueType($issue_type)
    {
        $this->set("issue_type", $issue_type);
        return $this;
    }

    /**
     * Gets the severity.
     *
     * @return string
     *   The severity.
     */
    public function getSeverity()
    {
        return $this->get("severity")->value;
    }

    /**
     * Sets the severity.
     *
     * @param string $severity
     *   The severity.
     *
     * @return $this
     */
    public function setSeverity($severity)
    {
        $this->set("severity", $severity);
        return $this;
    }

    /**
     * Gets the URL.
     *
     * @return string
     *   The URL.
     */
    public function getUrl()
    {
        return $this->get("url")->value;
    }

    /**
     * Sets the URL.
     *
     * @param string $url
     *   The URL.
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->set("url", $url);
        return $this;
    }

    /**
     * Gets the element selector.
     *
     * @return string
     *   The element selector.
     */
    public function getElementSelector()
    {
        return $this->get("element_selector")->value;
    }

    /**
     * Sets the element selector.
     *
     * @param string $selector
     *   The element selector.
     *
     * @return $this
     */
    public function setElementSelector($selector)
    {
        $this->set("element_selector", $selector);
        return $this;
    }

    /**
     * Gets the browser information.
     *
     * @return string
     *   The browser information.
     */
    public function getBrowserInfo()
    {
        return $this->get("browser_info")->value;
    }

    /**
     * Sets the browser information.
     *
     * @param string $browser_info
     *   The browser information.
     *
     * @return $this
     */
    public function setBrowserInfo($browser_info)
    {
        $this->set("browser_info", $browser_info);
        return $this;
    }

    /**
     * Gets the status.
     *
     * @return string
     *   The status.
     */
    public function getStatus()
    {
        return $this->get("status")->value;
    }

    /**
     * Sets the status.
     *
     * @param string $status
     *   The status.
     *
     * @return $this
     */
    public function setStatus($status)
    {
        $this->set("status", $status);
        return $this;
    }

    /**
     * Gets the description.
     *
     * @return string
     *   The description.
     */
    public function getDescription()
    {
        return $this->get("description")->value;
    }

    /**
     * Sets the description.
     *
     * @param string $description
     *   The description.
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->set("description", $description);
        return $this;
    }

    /**
     * Gets the admin comments.
     *
     * @return string
     *   The admin comments.
     */
    public function getAdminComments()
    {
        return $this->get("admin_comments")->value;
    }

    /**
     * Sets the admin comments.
     *
     * @param string $comments
     *   The admin comments.
     *
     * @return $this
     */
    public function setAdminComments($comments)
    {
        $this->set("admin_comments", $comments);
        return $this;
    }
    
    /**
     * Gets the file attachment.
     *
     * @return string
     *   The file attachment URI.
     */
    public function getFileAttachment()
    {
        return $this->get("file_attachment")->value;
    }

    /**
     * Sets the file attachment.
     *
     * @param string $file_uri
     *   The file attachment URI.
     *
     * @return $this
     */
    public function setFileAttachment($file_uri)
    {
        $this->set("file_attachment", $file_uri);
        return $this;
    }
}
