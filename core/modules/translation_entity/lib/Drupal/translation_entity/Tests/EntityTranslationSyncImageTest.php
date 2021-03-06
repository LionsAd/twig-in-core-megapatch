<?php

/**
 * @file
 * Contains \Drupal\entity\Tests\EntityTranslationSyncImageTest.
 */

namespace Drupal\translation_entity\Tests;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;

/**
 * Tests the Entity Translation image field synchronization capability.
 */
class EntityTranslationSyncImageTest extends EntityTranslationTestBase {

  /**
   * The cardinality of the image field.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * The test image files.
   *
   * @var array
   */
  protected $files;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('language', 'translation_entity', 'entity_test', 'image');

  public static function getInfo() {
    return array(
      'name' => 'Image field synchronization',
      'description' => 'Tests the field synchronization behavior for the image field.',
      'group' => 'Entity Translation UI',
    );
  }

  function setUp() {
    parent::setUp();
    $this->files = $this->drupalGetTestFiles('image');
  }

  /**
   * Creates the test image field.
   */
  protected function setupTestFields() {
    $this->fieldName = 'field_test_et_ui_image';
    $this->cardinality = 3;

    $field = array(
      'field_name' => $this->fieldName,
      'type' => 'image',
      'cardinality' => $this->cardinality,
      'translatable' => TRUE,
    );
    field_create_field($field);

    $instance = array(
      'entity_type' => $this->entityType,
      'field_name' => $this->fieldName,
      'bundle' => $this->entityType,
      'label' => 'Test translatable image field',
      'widget' => array(
        'type' => 'image_image',
        'weight' => 0,
      ),
      'settings' => array(
        'translation_sync' => array(
          'file' => FALSE,
          'alt' => 'alt',
          'title' => 'title',
        ),
      ),
    );
    field_create_instance($instance);
  }

  /**
   * Tests image field field synchronization.
   */
  function testImageFieldSync() {
    $default_langcode = $this->langcodes[0];
    $langcode = $this->langcodes[1];

    // Populate the required contextual values.
    $attributes = drupal_container()->get('request')->attributes;
    $attributes->set('working_langcode', $langcode);
    $attributes->set('source_langcode', $default_langcode);

    // Populate the test entity with some random initial values.
    $values = array(
      'name' => $this->randomName(),
      'user_id' => mt_rand(1, 128),
      'langcode' => $default_langcode,
    );
    $entity = entity_create($this->entityType, $values)->getBCEntity();

    // Create some file entities from the generated test files and store them.
    $values = array();
    for ($delta = 0; $delta < $this->cardinality; $delta++) {
      // For the default language use the same order for files and field items.
      $index = $delta;

      // Create the file entity for the image being processed and record its
      // identifier.
      $field_values = array(
        'uri' => $this->files[$index]->uri,
        'uid' => $GLOBALS['user']->uid,
        'status' => FILE_STATUS_PERMANENT,
      );
      $file = entity_create('file', $field_values);
      $file->save();
      $fid = $file->id();
      $this->files[$index]->fid = $fid;

      // Generate the item for the current image file entity and attach it to
      // the entity.
      $item = array(
        'fid' => $fid,
        'alt' => $this->randomName(),
        'title' => $this->randomName(),
      );
      $entity->{$this->fieldName}[$default_langcode][$delta] = $item;

      // Store the generated values keying them by fid for easier lookup.
      $values[$default_langcode][$fid] = $item;
    }
    $entity = $this->saveEntity($entity);

    // Create some field translations for the test image field. The translated
    // items will be one less than the original values to check that only the
    // translated ones will be preserved. In fact we want the same fids and
    // items order for both languages.
    for ($delta = 0; $delta < $this->cardinality - 1; $delta++) {
      // Simulate a field reordering: items are shifted of one position ahead.
      // The modulo operator ensures we start from the beginning after reaching
      // the maximum allowed delta.
      $index = ($delta + 1) % $this->cardinality;

      // Generate the item for the current image file entity and attach it to
      // the entity.
      $fid = $this->files[$index]->fid;
      $item = array(
        'fid' => $fid,
        'alt' => $this->randomName(),
        'title' => $this->randomName(),
      );
      $entity->{$this->fieldName}[$langcode][$delta] = $item;

      // Again store the generated values keying them by fid for easier lookup.
      $values[$langcode][$fid] = $item;
    }

    // Perform synchronization: the translation language is used as source,
    // while the default langauge is used as target.
    $entity = $this->saveEntity($entity);

    // Check that one value has been dropped from the original values.
    $assert = count($entity->{$this->fieldName}[$default_langcode]) == 2;
    $this->assertTrue($assert, 'One item correctly removed from the synchronized field values.');

    // Check that fids have been synchronized and translatable column values
    // have been retained.
    $fids = array();
    foreach ($entity->{$this->fieldName}[$default_langcode] as $delta => $item) {
      $value = $values[$default_langcode][$item['fid']];
      $source_item = $entity->{$this->fieldName}[$langcode][$delta];
      $assert = $item['fid'] == $source_item['fid'] && $item['alt'] == $value['alt'] && $item['title'] == $value['title'];
      $this->assertTrue($assert, format_string('Field item @fid has been successfully synchronized.', array('@fid' => $item['fid'])));
      $fids[$item['fid']] = TRUE;
    }

    // Check that the dropped value is the right one.
    $removed_fid = $this->files[0]->fid;
    $this->assertTrue(!isset($fids[$removed_fid]), format_string('Field item @fid has been correctly removed.', array('@fid' => $removed_fid)));

    // Add back an item for the dropped value and perform synchronization again.
    // @todo Actually we would need to reset the contextual information to test
    //   an update, but there is no entity field class for image fields yet,
    //   hence field translation update does not work properly for those.
    $values[$langcode][$removed_fid] = array(
      'fid' => $removed_fid,
      'alt' => $this->randomName(),
      'title' => $this->randomName(),
    );
    $entity->{$this->fieldName}[$langcode] = array_values($values[$langcode]);
    $entity = $this->saveEntity($entity);

    // Check that the value has been added to the default language.
    $assert = count($entity->{$this->fieldName}[$default_langcode]) == 3;
    $this->assertTrue($assert, 'One item correctly added to the synchronized field values.');

    foreach ($entity->{$this->fieldName}[$default_langcode] as $delta => $item) {
      // When adding an item its value is copied over all the target languages,
      // thus in this case the source language needs to be used to check the
      // values instead of the target one.
      $fid_langcode = $item['fid'] != $removed_fid ? $default_langcode : $langcode;
      $value = $values[$fid_langcode][$item['fid']];
      $source_item = $entity->{$this->fieldName}[$langcode][$delta];
      $assert = $item['fid'] == $source_item['fid'] && $item['alt'] == $value['alt'] && $item['title'] == $value['title'];
      $this->assertTrue($assert, format_string('Field item @fid has been successfully synchronized.', array('@fid' => $item['fid'])));
    }
  }

  /**
   * Saves the passed entity and reloads it, enabling compatibility mode.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be saved.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The saved entity.
   */
  protected function saveEntity(EntityInterface $entity) {
    $entity->save();
    $entity = entity_test_mul_load($entity->id(), TRUE);
    return $entity->getBCEntity();
  }

}
