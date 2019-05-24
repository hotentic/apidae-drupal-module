<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'ao picture' formatter.
 *
 * @FieldFormatter (
 *   id = "ao_picture_formatter",
 *   label = @Translation("AO Picture"),
 *   field_types = {
 *     "ao_picture"
 *   }
 * )
 */
class AoPictureFormatter extends FormatterBase
{

  /**
   * Builds a renderable array for a field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array for $items, as an array of child elements keyed by
   *   consecutive numeric indexes starting from 0.
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $elements = array();
    foreach ($items as $delta => $item) {

      $source = array(
        '#markup' => '<img src="' . $item->url_medium . '" alt="' . $item->title . '" />',
        '#title' => $item->title,
        '#credits' => $item->type,
        '#url_large' => $item->url_large,
        '#url_medium' => $item->url_medium,
        '#url_small' => $item->url_small,
      );

      $elements[$delta] = $source;
    }

    return $elements;
  }
}