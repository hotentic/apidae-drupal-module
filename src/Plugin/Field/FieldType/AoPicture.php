<?php

namespace Drupal\apidae_drupal_module\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of ao_picture.
 *
 * @FieldType(
 *   id = "ao_picture",
 *   label = @Translation("AO Picture field"),
 *   default_formatter = "ao_picture_formatter",
 *   default_widget = "ao_picture_widget",
 * )
 */
class AoPicture extends FieldItemBase
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
        'credits' => array(
          'type' => 'text',
          'not null' => FALSE,
        ),
        'url_large' => array(
          'type' => 'text',
          'not null' => FALSE,
        ),
        'url_medium' => array(
          'type' => 'text',
          'not null' => FALSE,
        ),
        'url_small' => array(
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
    $value1 = $this->get('url_large')->getValue();
    $value2 = $this->get('url_medium')->getValue();
    $value3 = $this->get('url_small')->getValue();
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
      ->setDescription(t('The title of the picture'));

    $properties['credits'] = DataDefinition::create('string')
      ->setLabel(t('Credits'))
      ->setDescription(t('The credits / copyright of the picture'));

    $properties['url_large'] = DataDefinition::create('string')
      ->setLabel(t('Picture URL (large format)'))
      ->setDescription(t('The URL of the picture (large format)'));

    $properties['url_medium'] = DataDefinition::create('string')
      ->setLabel(t('Picture URL (medium format)'))
      ->setDescription(t('The URL of the picture (medium format)'));

    $properties['url_small'] = DataDefinition::create('string')
      ->setLabel(t('Picture URL (small format)'))
      ->setDescription(t('The URL of the picture (small format)'));

    return $properties;
  }
}

