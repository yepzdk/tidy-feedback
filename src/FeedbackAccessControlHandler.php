<?php

namespace Drupal\tidy_feedback;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Feedback entity.
 */
class FeedbackAccessControlHandler extends EntityAccessControlHandler
{
    /**
     * {@inheritdoc}
     */
    protected function checkAccess(
        EntityInterface $entity,
        $operation,
        AccountInterface $account
    ) {
        // The admin user can do everything.
        if ($account->hasPermission("administer tidy feedback")) {
            return AccessResult::allowed()->cachePerPermissions();
        }

        switch ($operation) {
            case "view":
                return AccessResult::allowedIfHasPermission(
                    $account,
                    "view tidy feedback reports"
                );

            case "update":
                return AccessResult::allowedIfHasPermission(
                    $account,
                    "administer tidy feedback"
                );

            case "delete":
                return AccessResult::allowedIfHasPermission(
                    $account,
                    "administer tidy feedback"
                );
        }

        return AccessResult::neutral();
    }

    /**
     * {@inheritdoc}
     */
    protected function checkCreateAccess(
        AccountInterface $account,
        array $context,
        $entity_bundle = null
    ) {
        // Allow users with the 'access tidy feedback' permission to create feedback.
        return AccessResult::allowedIfHasPermission(
            $account,
            "access tidy feedback"
        );
    }
}
