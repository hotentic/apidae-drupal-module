<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of ao_booking_contact.
 *
 * @FieldType(
 *   id = "ao_booking_contact",
 *   label = @Translation("AO booking contact field"),
 *   default_formatter = "ao_booking_contact_formatter",
 *   default_widget = "ao_booking_contact_widget",
 * )
 */
class AoBookingContact extends FieldItemBase
{

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition)
  {
    return array(
      'columns' => array(
        'coordonnees' => array(
          'type' => 'text',
          'not null' => FALSE,
        ),
        'observation' => array(
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
    $value1 = $this->get('coordonnees')->getValue();
    $value2 = $this->get('observation')->getValue();
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
    $properties['coordonnees'] = DataDefinition::create('string')
      ->setLabel(t('Coordonnees'))
      ->setDescription(t('Booking contact'));

    $properties['observation'] = DataDefinition::create('string')
      ->setLabel(t('Observation'))
      ->setDescription(t('Contact Observation'));


    return $properties;
  }
}

