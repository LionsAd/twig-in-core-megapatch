<?php

/**
 * @file
 * Contains \Drupal\image\Form\ImageEffectDeleteForm.
 */

namespace Drupal\image\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\image\Plugin\Core\Entity\ImageStyle;

/**
 * Form for deleting an image effect.
 */
class ImageEffectDeleteForm extends ConfirmFormBase {

  /**
   * The image style containing the image effect to be deleted.
   *
   * @var \Drupal\image\Plugin\Core\Entity\ImageStyle
   */
  protected $imageStyle;

  /**
   * The image effect to be deleted.
   *
   * @var array;
   */
  protected $imageEffect;

  /**
   * {@inheritdoc}
   */
  protected function getQuestion() {
    return t('Are you sure you want to delete the @effect effect from the %style style?', array('%style' => $this->imageStyle->label(), '@effect' => $this->imageEffect['label']));
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  protected function getCancelPath() {
    return 'admin/config/media/image-styles/manage/' . $this->imageStyle->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'image_effect_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $image_style = NULL, $image_effect = NULL) {
    $this->imageStyle = $image_style;
    $this->imageEffect = image_effect_load($image_effect, $this->imageStyle->id());

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    image_effect_delete($this->imageStyle, $this->imageEffect);
    drupal_set_message(t('The image effect %name has been deleted.', array('%name' => $this->imageEffect['label'])));
    $form_state['redirect'] = 'admin/config/media/image-styles/manage/' . $this->imageStyle->id();
  }

}
