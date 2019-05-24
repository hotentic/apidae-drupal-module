<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'ao attachment' formatter.
 *
 * @FieldFormatter (
 *   id = "ao_attachment_formatter",
 *   label = @Translation("AO Attachment"),
 *   field_types = {
 *     "ao_attachment"
 *   }
 * )
 */
class AoAttachmentFormatter extends FormatterBase
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
        '#markup' => '<a href="' . $item->url . '" target="_blank">' . $item->name . '</a>',
        '#name' => $item->name,
        '#type' => $item->type,
        '#url' => $item->url,
        '#credits' => $item->credits,
        '#description' => $item->description
      );

      $elements[$delta] = $source;
    }

    return $elements;
  }
}