<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of aoPath.
 *
 * @FieldType(
 *   id = "ao_path",
 *   label = @Translation("AO Path field"),
 *   default_formatter = "ao_path_formatter",
 *   default_widget = "ao_path_widget",
 * )
 */
class AoPath extends FieldItemBase
{

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        return array(
            'columns' => array(
                'elevationGain' => array(
                    'type' => 'float',
                    'not null' => FALSE,
                ),
                'type' => array(
                    'type' => 'varchar',
                    'length' => 80,
                    'not null' => FALSE,
                ),
                'description' => array(
                    'type' => 'text',
                    'not null' => FALSE,
                ),
                'duration' => array(
                    'type' => 'int',
                    'not null' => FALSE,
                ),
                'distance' => array(
                    'type' => 'float',
                    'not null' => FALSE,
                ),
                'waymarked' => array(
                    'type' => 'varchar',
                    'length' => 80,
                    'not null' => FALSE,
                ),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $value1 = $this->get('type')->getValue();
        return empty($value1);
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
        $properties['elevationGain'] = DataDefinition::create('float')
            ->setLabel(t('Elevation gain'))
            ->setDescription(t('The elevation gain of the path'));

        $properties['type'] = DataDefinition::create('string')
            ->setLabel(t('Type'))
            ->setDescription(t('The type of the path'));

        $properties['description'] = DataDefinition::create('string')
            ->setLabel(t('Description'))
            ->setDescription(t('The description of the path'));

        $properties['duration'] = DataDefinition::create('integer')
            ->setLabel(t('Duration'))
            ->setDescription(t('The duration of the path in minutes'));

        $properties['distance'] = DataDefinition::create('float')
            ->setLabel(t('Distance'))
            ->setDescription(t('The distance of the path in km'));

        $properties['waymarked'] = DataDefinition::create('string')
            ->setLabel(t('Waymarked'))
            ->setDescription(t('Waymarks info'));

        return $properties;
    }
}

