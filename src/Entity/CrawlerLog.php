<?php

namespace Drupal\crawler\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the crawler entity.
 *
 * @ingroup crawler
 *
 * @ContentEntityType(
 *   id = "crawler",
 *   label = @Translation("crawler"),
 *   base_table = "crawler",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class CrawlerLog extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the CrawlerLog.php entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the CrawlerLog.php entity.'))
      ->setReadOnly(TRUE);

    $fields['site'] = BaseFieldDefinition::create('string')
      ->setLabel(t('URI of website'))
      ->setDescription(t('URI of website.'))
      ->setTranslatable(TRUE)
      ->setSettings([
        'default_value' => '',
      ]);

    $fields['message'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Message'))
      ->setDescription(t('Log message.'))
      ->setTranslatable(TRUE)
      ->setSettings([
        'default_value' => '',
      ]);
    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Message status'))
      ->setDescription(t('ERROR or just event'))
      ->setTranslatable(TRUE)
      ->setSettings([
        'default_value' => '',
      ]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->get('id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->get('uuid')->value;
  }

}
