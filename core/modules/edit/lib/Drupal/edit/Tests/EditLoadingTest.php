<?php

/**
 * @file
 * Contains \Drupal\edit\Tests\EditLoadingTest.
 */

namespace Drupal\edit\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\edit\Ajax\MetadataCommand;
use Drupal\Core\Ajax\AppendCommand;

/**
 * Tests loading of Edit and lazy-loading of in-place editors.
 */
class EditLoadingTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('contextual', 'edit', 'filter', 'node');

  public static function getInfo() {
    return array(
      'name' => 'In-place editing loading',
      'description' => 'Tests loading of in-place editing functionality and lazy loading of its in-place editors.',
      'group' => 'Edit',
    );
  }

  function setUp() {
    parent::setUp();

    // Create a text format.
    $filtered_html_format = entity_create('filter_format', array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => array(),
    ));
    $filtered_html_format->save();

    // Create a node type.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    // Create one node of the above node type using the above text format.
    $this->drupalCreateNode(array(
      'type' => 'article',
      'body' => array(
        0 => array(
          'value' => '<p>How are you?</p>',
          'format' => 'filtered_html',
        )
      )
    ));

    // Create 2 users, the only difference being the ability to use in-place
    // editing
    $basic_permissions = array('access content', 'create article content', 'edit any article content', 'use text format filtered_html', 'access contextual links');
    $this->author_user = $this->drupalCreateUser($basic_permissions);
    $this->editor_user = $this->drupalCreateUser(array_merge($basic_permissions, array('access in-place editing')));
  }

  /**
   * Test the loading of Edit when a user doesn't have access to it.
   */
  function testUserWithoutPermission() {
    $this->drupalLogin($this->author_user);
    $this->drupalGet('node/1');

    // Settings, library and in-place editors.
    $settings = $this->drupalGetSettings();
    $this->assertFalse(isset($settings['edit']), 'Edit settings do not exist.');
    $this->assertFalse(isset($settings['ajaxPageState']['js']['core/modules/edit/js/edit.js']), 'Edit library not loaded.');
    $this->assertFalse(isset($settings['ajaxPageState']['js']['core/modules/edit/js/createjs/editingWidgets/formwidget.js']), "'form' in-place editor not loaded.");

    // HTML annotation must always exist (to not break the render cache).
    $this->assertRaw('data-edit-entity="node/1"');
    $this->assertRaw('data-edit-id="node/1/body/und/full"');

    // Retrieving the metadata should result in an empty 403 response.
    $response = $this->retrieveMetadata(array('node/1/body/und/full'));
    $this->assertIdentical('{}', $response);
    $this->assertResponse(403);
  }

  /**
   * Tests the loading of Edit when a user does have access to it.
   *
   * Also ensures lazy loading of in-place editors works.
   */
  function testUserWithPermission() {
    $this->drupalLogin($this->editor_user);
    $this->drupalGet('node/1');

    // Settings, library and in-place editors.
    $settings = $this->drupalGetSettings();
    $this->assertTrue(isset($settings['edit']), 'Edit settings exist.');
    $this->assertTrue(isset($settings['ajaxPageState']['js']['core/modules/edit/js/edit.js']), 'Edit library loaded.');
    $this->assertFalse(isset($settings['ajaxPageState']['js']['core/modules/edit/js/createjs/editingWidgets/formwidget.js']), "'form' in-place editor not loaded.");

    // HTML annotation must always exist (to not break the render cache).
    $this->assertRaw('data-edit-entity="node/1"');
    $this->assertRaw('data-edit-id="node/1/body/und/full"');

    // Retrieving the metadata should result in a 200 response, containing:
    //  1. a settings command with correct in-place editor metadata
    //  2. an insert command that loads the required in-place editors
    //  3. a metadata command with correct per-field metadata
    $response = $this->retrieveMetadata(array('node/1/body/und/full'));
    $this->assertResponse(200);
    $ajax_commands = drupal_json_decode($response);
    $this->assertIdentical(3, count($ajax_commands), 'The metadata HTTP request results in three AJAX commands.');

    // First command: settings.
    $this->assertIdentical('settings', $ajax_commands[0]['command'], 'The first AJAX command is a settings command.');
    $edit_editors = array(
      'direct' => array('widget' => 'direct'),
      'form' => array('widget' => 'formEditEditor'),
    );
    $this->assertIdentical($edit_editors, $ajax_commands[0]['settings']['edit']['editors'], 'The settings command contains the expected settings.');

    // Second command: insert libraries into DOM.
    $this->assertIdentical('insert', $ajax_commands[1]['command'], 'The second AJAX command is an append command.');
    $command = new AppendCommand('body', '<script src="' . file_create_url('core/modules/edit/js/createjs/editingWidgets/formwidget.js') . '?v=' . VERSION . '"></script>' . "\n");
    $this->assertIdentical($command->render(), $ajax_commands[1], 'The append command contains the expected data.');

    // Third command: actual metadata.
    $this->assertIdentical('editMetadata', $ajax_commands[2]['command'], 'The third AJAX command is an Edit metadata command.');
    $command = new MetadataCommand(array(
      'node/1/body/und/full' => array(
        'label' => 'Body',
        'access' => TRUE,
        'editor' => 'form',
        'aria' => 'Entity node 1, field Body'
      )
    ));
    $this->assertIdentical($command->render(), $ajax_commands[2], 'The Edit metadata command contains the expected metadata.');
  }

  /**
   * Retrieve Edit metadata from the server. May also result in additional
   * JavaScript settings and CSS/JS being loaded.
   *
   * @param array $ids
   *   An array of edit ids.
   *
   * @return string
   *   The response body.
   */
  protected function retrieveMetadata($ids) {
    // Build POST values.
    $post = array();
    for ($i = 0; $i < count($ids); $i++) {
      $post['fields[' . $i . ']'] = $ids[$i];
    }

    // Serialize POST values.
    foreach ($post as $key => $value) {
      // Encode according to application/x-www-form-urlencoded
      // Both names and values needs to be urlencoded, according to
      // http://www.w3.org/TR/html4/interact/forms.html#h-17.13.4.1
      $post[$key] = urlencode($key) . '=' . urlencode($value);
    }
    $post = implode('&', $post);

    // Add extra information to the POST data as ajax.js does.
    $extra_post = '';
    $drupal_settings = $this->drupalSettings;
    if (isset($drupal_settings['ajaxPageState'])) {
      $extra_post .= '&' . urlencode('ajax_page_state[theme]') . '=' . urlencode($drupal_settings['ajaxPageState']['theme']);
      $extra_post .= '&' . urlencode('ajax_page_state[theme_token]') . '=' . urlencode($drupal_settings['ajaxPageState']['theme_token']);
      foreach ($drupal_settings['ajaxPageState']['css'] as $key => $value) {
        $extra_post .= '&' . urlencode("ajax_page_state[css][$key]") . '=1';
      }
      foreach ($drupal_settings['ajaxPageState']['js'] as $key => $value) {
        $extra_post .= '&' . urlencode("ajax_page_state[js][$key]") . '=1';
      }
    }

    // Perform HTTP request.
    return $this->curlExec(array(
      CURLOPT_URL => url('edit/metadata', array('absolute' => TRUE)),
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $post . $extra_post,
      CURLOPT_HTTPHEADER => array(
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
      ),
    ));
   }
}
