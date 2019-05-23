<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'ao Link' formatter.
 *
 * @FieldFormatter (
 *   id = "ao_link_formatter",
 *   label = @Translation("AO Link"),
 *   field_types = {
 *     "ao_link"
 *   }
 * )
 */
class AoLinkFormatter extends FormatterBase
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
//                '#theme' => 'ao_link_formatter',
                '#markup' => $item->title,
                '#title' => $item->title,
                '#type' => $item->type,
                '#url' => $item->url,
            );

            $elements[$delta] = $source;
        }

        return $elements;
    }
}