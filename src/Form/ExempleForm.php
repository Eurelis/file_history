<?php

namespace Drupal\file_history\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DefaultForm.
 */
class ExempleForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'exemple_file_history.default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exemple_file_history_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('file_history.default');
    $class = get_class($this);
    $validators = [
      'file_validate_extensions' => ['xls xlsx'],
      'file_validate_size' => [file_upload_max_size()],
    ];

    $form['configurations_files'] = [
      '#type' => 'file_history',
      '#title' => $this->t('Configurations'),
      '#description' => $this->t('List of files'),
      '#default_value' => $config->get('configurations'),
      '#size' => 50,
      // Like Managed Files, general file validation.
      '#upload_validators' => $validators,
      // Folder to store files.
      '#upload_location' => 'public://my_configuration/',
      // If you need validation content of files before store it.
      '#content_validator' => [
        $class, 'myContentValidator',
      ],

    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * Validation of the upload canditat files.
   *
   * @param array $file_data
   *   Data of uploaded file.
   *
   * @return array
   *   Validation status.
   */
  public static function myContentValidator(array $file_data = []) {
    /*
     * $file_data = [
     *   'file_original_name' => string
     *   'file_original_extension' => string
     *   'file_size' => integer
     *   'file_path' => string(14)
     */
    // Deepest file validation.
    /*
     * Return value = [
     *  'status' => Boolean ( True => ok , False => error)
     *  'message' => string ( message to user )
     * ]
     */
    $status = TRUE;
    $message = 'OK';
    return ['status' => $status, 'message' => $message];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $selected_file_value = $form_state->getValue('configurations_files');
    /*
     * $selected_file_value = [
     *  'selected_file => string ( Fid )
     *  'upload' => string ( empty)
     *  'upload_button' => TranslatableMarkup
     * ];
     *
     */

    // Do something on submit.
    $this->config('exemple_file_history.default')
      ->set('selected_configuration_file', $selected_file_value['selected_file'])
      ->save();
  }

}