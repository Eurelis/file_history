<?php

namespace Drupal\file_history\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Url;

/**
 * Provides an widget with memory of uploaded file.
 *
 * @FormElement("file_history")
 */
class FileHistory extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processFileHistory'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#progress_indicator' => 'throbber',
      '#progress_message' => NULL,
      '#upload_validators' => [],
      '#upload_location' => NULL,
      '#size' => 22,
      '#multiple' => FALSE,
      '#extended' => FALSE,
      '#attached' => [
        'library' => ['file/drupal.file'],
      ],
      '#accept' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // If no upload_location, no field.
    if ($element['#upload_location'] == NULL) {
      drupal_set_message("'#upload_location' attribute are mandatory in file_history definition.", 'error');
      return;
    }

    // If there is input.
    if ($input !== FALSE) {

      $block_upload = FALSE;
      // If isset file content validation.
      if (isset($element['#content_validator']) && is_callable($element['#content_validator'], FALSE, $validation_callback)) {
        $all_files = \Drupal::request()->files->get('files', []);
        $upload_name = implode('_', $element['#parents']);
        if (!empty($all_files[$upload_name]) && file_exists($all_files[$upload_name])) {

          /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file_upload */
          $file_upload = $all_files[$upload_name];
          $file_data_for_validation = [
            'file_original_name' => $file_upload->getClientOriginalName(),
            'file_original_extension' => $file_upload->getClientOriginalExtension(),
            'file_size' => $file_upload->getClientSize(),
            'file_path' => $file_upload->getRealPath(),
          ];

          $return_status = $validation_callback($file_data_for_validation);

          if (!isset($return_status['status'])) {
            drupal_set_message("Validation callback need return at least a status 'return ['status' => Boolean]'", 'error');
            return;
          }

          $status = 'status';
          if ($return_status['status'] === FALSE) {
            $block_upload = TRUE;
            $status = 'error';
          }

          if (isset($return_status['message']) && $return_status['message'] != '') {
            drupal_set_message($return_status['message'], $status);
          }

          // If validation failed.
          if ($return_status['status'] === FALSE) {
            $block_upload = TRUE;
          }
        }
      }

      if ($block_upload !== TRUE) {
        // Upload File.
        if ($files = file_managed_file_save_upload($element, $form_state)) {
          // Set file as permanent.
          /** @var \Drupal\file\Entity\File $file */
          foreach ($files as $file) {
            if ($file != NULL && $file->isTemporary()) {
              $file->setPermanent();
              $file->save();
            }
          }
        }
      }
    }

    // Return default selected value.
    $config_name = 'remember_files.' . $element['#name'];
    $config = \Drupal::config($config_name);
    $fid = $config->get('activ_file');
    return ['selected_file' => $fid];
  }

  /**
   * Render API callback: Expands the managed_file element type.
   *
   * Expands the file type to include Upload and Remove buttons, as well as
   * support for a default value.
   */
  public static function processFileHistory(&$element, FormStateInterface $form_state, &$complete_form) {
    // If no upload_location, no field.
    if ($element['#upload_location'] == NULL) {
      return;
    }
    // Prepare upload fields.
    // This is used sometimes so let's implode it just once.
    $parents_prefix = implode('_', $element['#parents']);
    $element['#tree'] = TRUE;

    // Add Upload field.
    $element['upload'] = [
      '#name' => 'files[' . $parents_prefix . ']',
      '#type' => 'file',
      '#title' => t('Choose a file'),
      '#title_display' => 'invisible',
      '#size' => $element['#size'],
      '#multiple' => $element['#multiple'],
      '#theme_wrappers' => [],
      '#weight' => -10,
      '#error_no_message' => TRUE,
    ];

    // Add upload button.
    $element[$parents_prefix . '_upload_button'] = [
      '#name' => $parents_prefix . '_upload_button',
      '#type' => 'submit',
      '#value' => t('Upload'),
      '#validate' => [],
      '#submit' => ['file_history_submit_upload'],
      '#limit_validation_errors' => [],
      '#weight' => -5,
    ];

    $file_extension_mask = '/./';
    // Add the extension list to the page as JavaScript settings.
    if (isset($element['#upload_validators']['file_validate_extensions'][0])) {
      $extension_list = implode(',', array_filter(explode(' ', $element['#upload_validators']['file_validate_extensions'][0])));
      $element['upload']['#attached']['drupalSettings']['file']['elements']['#' . $element['#id']] = $extension_list;

      $file_extension_mask = '/.*\.' . str_replace(' ', '|', $element['#upload_validators']['file_validate_extensions'][0]) . '/';
    }

    // Get config for Current File.
    $config_name = 'remember_files.' . $element['#name'];
    $config = \Drupal::config($config_name);

    // Add Table Header.
    $header = [
      ['data' => t('Name')],
      ['data' => t('Filename')],
      ['data' => t('Uploaded at')],
      ['data' => t('Is active file ?')],
      ['data' => t('Operations')],
    ];

    $rows = [];

    // List only files with correct extensions.
    $already_load_files = file_scan_directory($element['#upload_location'], $file_extension_mask);
    $currentFile = $config->get('activ_file');

    // For Each files.
    foreach ($already_load_files as $file) {

      $fObj = self::getFileFromUri($file->uri);

      if ($fObj == NULL) {
        continue;
      }
      $fid = $fObj->id();

      $fileRow = [];
      $fileRow[] = ['data' => $file->name];
      $fileRow[] = ['data' => $file->filename];
      $fileRow[] = ['data' => date('Y-m-d H:i', $fObj->getCreatedTime())];

      $isCurrentFile = ($fid == $currentFile);

      $fileRow[] = ['data' => $isCurrentFile ? t('Yes') : ''];

      $current_route = \Drupal::routeMatch()->getRouteName();

      if ($isCurrentFile === TRUE) {
        $link_title = t('Reload');
        $route_target = 'use_file';
      }
      else {
        $link_title = t('Use');
        $route_target = 'reload_file';
      }

      $links = [];
      $links[] = [
        'title' => $link_title ,
        'url' => Url::fromRoute('file_history.' . $route_target,
          [
            'file' => $fid,
            'config_name' => $element['#name'],
            'destination' => $current_route,
          ]),
      ];

      if (!$isCurrentFile) {
        $links[] = [
          'title' => t('Delete'),
          'url' => Url::fromRoute('file_history.delete_file',
            [
              'file' => $fid,
              'destination' => $current_route,
            ]),
        ];
      }

      $fileRow[] = [
        'data' =>
        [
          '#type' => 'dropbutton',
          '#links' => $links,
        ],
      ];
      $rows[$fObj->getCreatedTime()] = $fileRow;
    }

    // We sort files by upload time.
    krsort($rows);

    $element['table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => array_values($rows),
    ];

    return $element;
  }

  /**
   * Method to retrieve a file object given an uri.
   *
   * @param string $uri
   *   Uri of file.
   *
   * @return \Drupal\file\FileInterface|null
   *   returns a file given the uri.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function getFileFromUri($uri) {
    $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri]);
    if (!empty($files)) {
      $fileArray = array_values($files);
      return $fileArray[0];
    }
    return NULL;
  }

}
