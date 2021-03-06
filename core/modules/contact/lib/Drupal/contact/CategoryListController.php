<?php

/**
 * Definition of Drupal\contact\CategoryListController.
 */

namespace Drupal\contact;

use Drupal\Core\Config\Entity\ConfigEntityListController;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of contact categories.
 */
class CategoryListController extends ConfigEntityListController {

  /**
   * Overrides Drupal\Core\Entity\EntityListController::getOperations().
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    if (module_exists('field_ui')) {
      $uri = $entity->uri();
      $operations['manage-fields'] = array(
        'title' => t('Manage fields'),
        'href' => $uri['path'] . '/fields',
        'options' => $uri['options'],
        'weight' => 11,
      );
      $operations['manage-display'] = array(
        'title' => t('Manage display'),
        'href' => $uri['path'] . '/display',
        'options' => $uri['options'],
        'weight' => 12,
      );
    }
    return $operations;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityListController::buildHeader().
   */
  public function buildHeader() {
    $row['category'] = t('Category');
    $row['recipients'] = t('Recipients');
    $row['selected'] = t('Selected');
    $row['operations'] = t('Operations');
    return $row;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityListController::buildRow().
   */
  public function buildRow(EntityInterface $entity) {
    $row['category'] = check_plain($entity->label());
    $row['recipients'] = check_plain(implode(', ', $entity->recipients));
    $default_category = config('contact.settings')->get('default_category');
    $row['selected'] = ($default_category == $entity->id() ? t('Yes') : t('No'));
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row;
  }

}
