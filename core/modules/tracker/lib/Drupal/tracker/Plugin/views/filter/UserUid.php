<?php

/**
 * @file
 * Contains \Drupal\tracker\Plugin\views\filter\UserUid.
 */

namespace Drupal\tracker\Plugin\views\filter;

use Drupal\Component\Annotation\PluginID;
use Drupal\user\Plugin\views\filter\Name;

/**
 * UID filter to check for nodes that a user posted or commented on.
 *
 * @ingroup views_filter_handlers
 *
 * @PluginID("tracker_user_uid")
 */
class UserUid extends Name {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Because this handler thinks it's an argument for a field on the {node}
    // table, we need to make sure {tracker_user} is JOINed and use its alias
    // for the WHERE clause.
    $tracker_user_alias = $this->query->ensure_table('tracker_user');
    $this->query->add_where(0, "$tracker_user_alias.uid", $this->value);
  }

}
