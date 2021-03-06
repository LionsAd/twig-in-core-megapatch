<?php

/**
 * @file
 * Contains \Drupal\views\Tests\Plugin\ExposedFormTest.
 */

namespace Drupal\views\Tests\Plugin;

use Drupal\views\Tests\ViewTestBase;

/**
 * Tests exposed forms.
 */
class ExposedFormTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = array('test_reset_button');

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('views_ui');

  public static function getInfo() {
    return array(
      'name' => 'Exposed forms',
      'description' => 'Test exposed forms functionality.',
      'group' => 'Views Plugins',
    );
  }

  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'article'));

    // Create some random nodes.
    for ($i = 0; $i < 5; $i++) {
      $this->drupalCreateNode();
    }
  }

  /**
   * Tests whether the reset button works on an exposed form.
   */
  public function testResetButton() {
    $this->drupalGet('test_reset_button', array('query' => array('type' => 'article')));
    // Test that the type has been set.
    $this->assertFieldById('edit-type', 'article', 'Article type filter set.');

    // Test the reset works.
    $this->drupalGet('test_reset_button', array('query' => array('op' => 'Reset')));
    $this->assertResponse(200);
    // Test the type has been reset.
    $this->assertFieldById('edit-type', 'All', 'Article type filter has been reset.');
  }

  /**
   * Tests, whether and how the reset button can be renamed.
   */
  public function testRenameResetButton() {
    // Look at the page and check the label "reset".
    $this->drupalGet('test_reset_button');
    // Rename the label of the reset button.
    $view = views_get_view('test_reset_button');
    $view->setDisplay();

    $exposed_form = $view->display_handler->getOption('exposed_form');
    $exposed_form['options']['reset_button_label'] = $expected_label = $this->randomName();
    $exposed_form['options']['reset_button'] = TRUE;
    $view->display_handler->setOption('exposed_form', $exposed_form);
    $view->save();

    views_invalidate_cache();

    // Look whether ther reset button label changed.
    $this->drupalGet('test_reset_button');
    $this->assertResponse(200);

    $this->helperButtonHasLabel('edit-reset', $expected_label);
  }

  /**
   * Tests the exposed form markup.
   */
  public function testExposedFormRender() {
    $view = views_get_view('test_reset_button');
    $this->executeView($view);
    $exposed_form = $view->display_handler->getPlugin('exposed_form');
    $this->drupalSetContent($exposed_form->render_exposed_form());

    $expected_id = drupal_clean_css_identifier('views-exposed-form-' . $view->storage->id() . '-' . $view->current_display);
    $this->assertFieldByXpath('//form/@id', $expected_id, 'Expected form ID found.');

    $expected_action = url($view->display_handler->getUrl());
    $this->assertFieldByXPath('//form/@action', $expected_action, 'The expected value for the action attribute was found.');
  }

}
