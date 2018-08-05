<?php

namespace Drupal\crawler\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CrawlerSettingsForm.
 *
 * @package Drupal\crawler\Form
 *
 * @ingroup crawler
 */
class CrawlerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'crawler_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['crawler.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('crawler.settings');

    $form['parsingUrls'] = [
      '#type' => 'textarea',
      '#title' => t('Urls list for parsing'),
      '#description' => t('Enter urls in the format: https://www.host1.com/, http://www.host2.com/, ... , http://www.hostN.com/'),
      '#default_value' => $config->get('parsingUrls'),
      '#rows' => 10,
    ];
    $form['category'] = [
      '#type' => 'textfield',
      '#title' => t('Category'),
      '#description' => t('Category field for sites'),
      '#default_value' => $config->get('category'),
      '#rows' => 1,
    ];
    $form['depthSearch'] = [
      '#type' => 'textfield',
      '#title' => t('Depth of search contacts in site'),
      '#description' => t('Enter integer number (not recomendated use number > 3).'),
      '#default_value' => $config->get('depthSearch'),
      '#rows' => 1,
    ];
    $form['parsePageLimit'] = [
      '#type' => 'textfield',
      '#title' => t('Parse page limit'),
      '#description' => t('100 pages ~ 140sec parsing'),
      '#default_value' => $config->get('parsePageLimit'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('crawler.settings');
    $config
      ->set('depthSearch', $form_state->getValue('depthSearch'))
      ->set('category', $form_state->getValue('category'))
      ->set('parsingUrls', $form_state->getValue('parsingUrls'))
      ->set('parsePageLimit', $form_state->getValue('parsePageLimit'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
