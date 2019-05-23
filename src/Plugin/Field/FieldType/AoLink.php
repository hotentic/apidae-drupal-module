<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of aoLink.
 *
 * @FieldType(
 *   id = "ao_link",
 *   label = @Translation("AO Link field"),
 *   default_formatter = "ao_link_formatter",
 *   default_widget = "ao_link_widget",
 * )
 */
class AoLink extends FieldItemBase
{

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        return array(
            'columns' => array(
                'title' => array(
                    'type' => 'text',
                    'not null' => FALSE,
                ),
                'type' => array(
                    'type' => 'text',
                    'not null' => FALSE,
                ),
                'url' => array(
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
        $value1 = $this->get('title')->getValue();
        $value2 = $this->get('type')->getValue();
        $value3 = $this->get('url')->getValue();
        return empty($value1) && empty($value2) && empty($value3);
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
        $properties['title'] = DataDefinition::create('string')
            ->setLabel(t('Title'))
            ->setDescription(t('The title of the linked object'));

        $properties['type'] = DataDefinition::create('string')
            ->setLabel(t('Type'))
            ->setDescription(t('The type of the linked object'));

        $properties['url'] = DataDefinition::create('string')
            ->setLabel(t('URL'))
            ->setDescription(t('The URL of the linked object node'));

        return $properties;
    }
}

