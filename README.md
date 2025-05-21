# Tidy Feedback

## INTRODUCTION

Tidy Feedback is a Drupal module that allows users to provide feedback on specific elements of a website.
It's designed for testers and content reviewers to easily highlight and comment
on parts of the site that need improvement.

## REQUIREMENTS

This module requires no modules outside of Drupal core.

## INSTALLATION

1. Install as you would normally install a contributed Drupal module.
   See: <https://www.drupal.org/docs/extending-drupal/installing-modules>
2. Visit the configuration page at `/admin/config/system/tidy-feedback`
   to configure module settings.

## CONFIGURATION

1. Configure user permissions in `/admin/people/permissions#module-tidy_feedback`
2. Configure module settings in `/admin/config/system/tidy-feedback`

## USAGE

1. Log in as a user with the "access tidy feedback" permission
2. Look for the feedback tab on the side of the screen
3. Click the tab to activate feedback mode
4. Hover over any element you want to provide feedback on
5. Click the element to open the feedback form
6. Submit your feedback with appropriate details

## ADMINISTRATION

Administrators can view, manage, and respond to feedback at `/admin/reports/tidy-feedback`
