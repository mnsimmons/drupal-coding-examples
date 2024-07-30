<?php

namespace Drupal\webform_to_gsheets\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Webform to Gsheets settings.
 */
class WebformToGsheetsSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'webform_to_gsheets.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_to_gsheets_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['spreadsheet_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Spreadsheet ID'),
      '#default_value' => $config->get('spreadsheet_id'),
    ];

    $form['google_service_credential_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google service credential file'),
      '#description' => $this->t('Provide the path for the Google service credential file.'),
      '#default_value' => $config->get('google_service_credential_file'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config(static::SETTINGS)
      // Set the submitted configuration setting.
      ->set('spreadsheet_id', $form_state->getValue('spreadsheet_id'))
      ->set('google_service_credential_file', $form_state->getValue('google_service_credential_file'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
