<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of ao_attachment.
 *
 * @FieldType(
 *   id = "ao_attachment",
 *   label = @Translation("AO attachment"),
 *   default_formatter = "ao_attachment_formatter",
 *   default_widget = "ao_attachment_widget",
 * )
 */
class AoAttachment extends FieldItemBase
{

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition)
  {
    return array(
      'columns' => array(
        'name' => array(
          'type' => 'text',
          'not null' => FALSE,
        ),
        'type' => array(
          'type' => 'varchar',
          'length' => 50,
          'not null' => FALSE,
        ),
        'url' => array(
          'type' => 'text',
          'not null' => FALSE,
        ),
        'credits' => array(
          'type' => 'text',
          'not null' => FALSE,
        ),
        'description' => array(
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
    $value1 = $this->get('name')->getValue();
    $value2 = $this->get('url')->getValue();

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
    $properties['name'] = DataDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the attachment'));

    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('The type of attachment'));

    $properties['url'] = DataDefinition::create('string')
      ->setLabel(t('Attachment URL'))
      ->setDescription(t('The URL of the attachment'));

    $properties['credits'] = DataDefinition::create('string')
      ->setLabel(t('Attachment credits / copyright'))
      ->setDescription(t('The credits / copyright info about the attachment'));

    $properties['description'] = DataDefinition::create('string')
      ->setLabel(t('Attachment description'))
      ->setDescription(t('Extra info about the attachment'));

    return $properties;
  }
}

