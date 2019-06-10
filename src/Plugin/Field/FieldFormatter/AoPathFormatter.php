<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'Ao Path' formatter.
 *
 * @FieldFormatter (
 *   id = "ao_path_formatter",
 *   label = @Translation("AO Path"),
 *   field_types = {
 *     "ao_path"
 *   }
 * )
 */
class AoPathFormatter extends FormatterBase
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
                '#markup' => $item->description,
                '#elevationGain' => $item->elevationGain,
                '#type' => $item->type,
                '#distance' => $item->distance,
                '#duration' => $item->duration,
                '#waymarked' => $item->waymarked,
            );

            $elements[$delta] = $source;
        }

        return $elements;
    }
}