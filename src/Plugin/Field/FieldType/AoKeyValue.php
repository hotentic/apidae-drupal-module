<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of ao Key Value.
 *
 * @FieldType(
 *   id = "ao_key_value",
 *   label = @Translation("AO Key Value field"),
 *   default_formatter = "ao_key_value_formatter",
 *   default_widget = "ao_key_value_widget",
 * )
 */
class AoKeyValue extends FieldItemBase
{

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        return array(
            'columns' => array(
                'key' => array(
                    'type' => 'text',
                    'not null' => FALSE,
                ),
                'value' => array(
                    'type' => 'text',
                    'not null' => FALSE,
                )
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $value1 = $this->get('key')->getValue();
        $value2 = $this->get('value')->getValue();
        return empty($value1) && empty($value2);
    }

    /**
     * Defines field item properties.
     *
     * Properties that are required to constitute a valid, non-empty item should
     * be denoted with \Drupal\Core\TypedData\DataDefinition::setRequired().
     *
     * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
     *   An array of property definitions of contained properties, keyed by
     *   property name.
     *
     * @see \Drupal\Core\Field\BaseFieldDefinition
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        // Add our properties.
        $properties['key'] = DataDefinition::create('string')
            ->setLabel(t('Key'))
            ->setDescription(t('KEY => value'));

        $properties['value'] = DataDefinition::create('string')
            ->setLabel(t('Value'))
            ->setDescription(t('key => VALUE'));

        return $properties;
    }
}

