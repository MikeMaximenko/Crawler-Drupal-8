<?php

namespace Drupal\crawler\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Contenttype module form for handle batch operation.
 */
class CrawlerForm extends FormBase {

  /**
   * Inheritdoc.
   */
  public function getFormId() {
    return 'crawler_form';
  }

  /**
   * Inheritdoc.
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
    $form['uploaded_file'] = [
      '#type' => 'managed_file',
      '#title' => t('Upload your svg file with urls'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start parse pages'),
      '#weight' => 100,
    ];
    return $form;
  }

  /**
   * Inheritdoc.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    parent::validateForm($form, $form_state);
  }

  /**
   * Inheritdoc.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $urls = [];
    $countries = [];
    $languages = [];
    $operations = [];

    $category = $form_state->getValue('category');

    /* Checking if was triggered file field */
    if ($file = $form_state->getValue('uploaded_file')[0]) {
      try {
        $file = File::load($file)->getFileUri();
        $file = fopen($file, "r");
      }
      catch (Exception $e) {
        $urls = NULL;
        drupal_set_message('Open file error', 'error');
      }

      /* Get sites url, country, languages from file. */
      while ($data_string = fgetcsv($file, 100, "\n")) {
        $data_string = explode(',', $data_string[0]);

        $urls[] = $data_string[0];
        $countries[] = $data_string[1];
        $languages[] = $data_string[2];
      }
      fclose($file);
    }
    else {
      $urls = $form_state->getValue('parsingUrls');
      $urls = preg_split("/[\s,]+/", $urls);
    }
    array_unique($urls);

    foreach ($urls as $key => $siteUrl) {
      if ($siteUrl) {
        $operations[] = ['\Drupal\crawler\BatchOperations::crawlerCallback', [$siteUrl, $category, $countries[$key], $languages[$key]]];
      }
    }

    $batch = [
      'title' => t('Parsing..'),
      'operations' => $operations,
      'finished' => '\Drupal\crawler\BatchOperations::crawlerFinishedCallback',
    ];

    batch_set($batch);
  }

}
