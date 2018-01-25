<?php

namespace Drupal\file_history\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Url;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;

/**
 * Provides an AJAX/progress aware widget for uploading and saving a file.
 *
 * @FormElement("file_history")
 */
class FileHistory extends ManagedFile {

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
      '#element_validate' => [
        [$class, 'validateFileHistory'],
      ],
      '#theme' => 'file_history',
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

    // If user need validation content of file
    if(isset($element['#content_validator']) && is_callable($element['#content_validator'], false, $validation_callback)) {

      $all_files = \Drupal::request()->files->get('files', []);
      $upload_name = implode('_', $element['#parents']);
      if(!empty($all_files[$upload_name])) {

        $file_upload = $all_files[$upload_name];

        $return_status = $validation_callback($file_upload);

        if(isset($return_status['message']) && $return_status['message'] != '') {
          drupal_set_message($return_status['message']);
        }
        // If validation failed
        if($return_status['status'] === FALSE) {
          return;
        }

      }
    }

    // Call ManagedFile valueCallback
    parent::valueCallback($element, $input, $form_state);

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

    // Call ManagedFile Process Callback
    parent::processManagedFile($element, $form_state, $complete_form);

    // Clean not used elements
    if($element['#files'] != FALSE) {
      foreach($element['#files'] as $delta => $file)  {
        unset($element['file_' . $delta]);
      }
    }
    unset($element['remove_button']);
    unset($element['fids']);

    // Get config for Current File
    $config_name = 'remember_files.' . $element['#name'];
    $config = \Drupal::config($config_name);

    // Add Table Header
    $header = [
      ['data' => t('Name')],
      ['data' => t('Filename')],
      ['data' => t('Uploaded at')],
      ['data' => t('Is active file ?')],
      ['data' => t('Operations')]
    ];

    $rows = [];

    $files = file_scan_directory($element['#upload_location'], '/.*\.xlsx$/');

    $currentFile = $config->get('activ_file');

    // For Each files
    foreach($files as $file) {

      $fObj = self::getFileFromUri($file->uri);

      if ($fObj == null) {
        continue;
      }
      $fid = $fObj->id();

      $fileRow = [];
      $fileRow[] = ['data' => $file->name ];
      $fileRow[] = ['data' => $file->filename  ];
      $fileRow[] = ['data' => date('Y-m-d H:i',$fObj->getCreatedTime())  ];

      $isCurrentFile = ($fid == $currentFile);

      $fileRow[] = ['data' => $isCurrentFile ? t('Yes') : '' ];

      $current_route = \Drupal::routeMatch()->getRouteName();

      $links = [];
      $links[] = [
        'title' => $isCurrentFile ? t('Reload') : t('Use'),
        'url' => Url::fromRoute('remember_files.use_file', ['file' => $fid, 'config_name' => $element['#name'], 'destination' => $current_route])
      ];

      if (!$isCurrentFile) {
        $links[] = [
          'title' => t('Delete'),
          'url' => Url::fromRoute('remember_files.delete_file', ['file' => $fid, 'destination' => $current_route])
        ];
      }

      $fileRow[] = ['data' =>
        [
          '#type' => 'dropbutton',
          '#links' => $links
        ]
      ];
      $rows[$fObj->getCreatedTime()] = $fileRow;
    }

    // We sort files by upload time
    krsort($rows);

    $element['table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => array_values($rows)
    ];

    return $element;
  }

  /**
   * Method to retrieve a file object given an uri
   * @param string $uri
   *
   * @return \Drupal\file\FileInterface|null returns a file given the uri
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

  /**
   * Instead of lack of element_submit, we use validate to make permanant loaded files
   *
   * @param $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $complete_form
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function validateFileHistory(&$element, FormStateInterface $form_state, &$complete_form) {
    // Check required property based on the FID.

    if ($element['#required']) {
      $config_name = 'remember_files.' . $element['#name'];
      $config = \Drupal::config($config_name);
      $currentFile = $config->get('activ_file');
/*
      if($currentFile == '') {
        // Field need a file are choosed
        $form_state->setError($element, t('Upload and/or choose en file for @name field.', ['@name' => $element['#title']]));
      }*/
    }

    // If no validation error on this element, we save files
    $files = file_scan_directory($element['#upload_location'], '/.*\.xlsx$/');
    foreach($files as $file) {
      $fObj = self::getFileFromUri($file->uri);
      if($fObj != NULL && $fObj->isTemporary()) {
        $fObj->setPermanent();
        $fObj->save();
      }
    }


  }
}
