<?php

/**
 * @file
 * Contains \Drupal\image\Plugin\field\formatter\ImageFormatter.
 */

namespace Drupal\image\Plugin\field\formatter;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Plugin implementation of the 'image' formatter.
 *
 * @Plugin(
 *   id = "image",
 *   module = "image",
 *   label = @Translation("Image"),
 *   field_types = {
 *     "image"
 *   },
 *   settings = {
 *     "image_style" = "",
 *     "image_link" = ""
 *   }
 * )
 */
class ImageFormatter extends FormatterBase {

  /**
   * Implements \Drupal\field\Plugin\Type\Formatter\FormatterInterface::settingsForm().
   */
  public function settingsForm(array $form, array &$form_state) {
    $image_styles = image_style_options(FALSE);
    $element['image_style'] = array(
      '#title' => t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
    );

    $link_types = array(
      'content' => t('Content'),
      'file' => t('File'),
    );
    $element['image_link'] = array(
      '#title' => t('Link image to'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link'),
      '#empty_option' => t('Nothing'),
      '#options' => $link_types,
    );

    return $element;
  }

  /**
   * Implements \Drupal\field\Plugin\Type\Formatter\FormatterInterface::settingsSummary().
   */
  public function settingsSummary() {
    $summary = array();

    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('image_style');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = t('Image style: @style', array('@style' => $image_styles[$image_style_setting]));
    }
    else {
      $summary[] = t('Original image');
    }

    $link_types = array(
      'content' => t('Linked to content'),
      'file' => t('Linked to file'),
    );
    // Display this setting only if image is linked.
    $image_link_setting = $this->getSetting('image_link');
    if (isset($link_types[$image_link_setting])) {
      $summary[] = $link_types[$image_link_setting];
    }

    return implode('<br />', $summary);
  }

  /**
   * Implements \Drupal\field\Plugin\Type\Formatter\FormatterInterface::viewElements().
   */
  public function viewElements(EntityInterface $entity, $langcode, array $items) {
    $elements = array();

    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting == 'content') {
      $uri = $entity->uri();
    }
    elseif ($image_link_setting == 'file') {
      $link_file = TRUE;
    }

    $image_style_setting = $this->getSetting('image_style');
    foreach ($items as $delta => $item) {
      if (isset($link_file)) {
        $uri = array(
          'path' => file_create_url($item['uri']),
          'options' => array(),
        );
      }
      $elements[$delta] = array(
        '#theme' => 'image_formatter',
        '#item' => $item,
        '#image_style' => $image_style_setting,
        '#path' => isset($uri) ? $uri : '',
      );
    }

    return $elements;
  }

}
