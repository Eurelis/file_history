<?php

namespace Drupal\file_history\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\FileInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Drupal\Core\Url;

/**
 * Class RememberFilesController.
 */
class FileHistoryController extends ControllerBase {

  /**
   * Set File for Use
   *
   * @param \Drupal\file\FileInterface $file
   * @param $config_name
   * @param $destination
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function useFile(FileInterface $file, $config_name, $destination) {

    //Set file as active
    $config = \Drupal::service('config.factory')->getEditable('remember_files.' . $config_name);
    $config->set('activ_file', $file->id())->save();

    return $this->returnToPage($destination);
  }

  /**
   * Delete File
   *
   * @param \Drupal\file\FileInterface $file
   * @param $destination
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteFile(FileInterface $file, $destination) {
    $file->delete();
    return $this->returnToPage($destination);
  }

  /**
   * Return to parent page
   *
   * @param $destination
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  private function returnToPage($destination) {
    $url = Url::fromRoute($destination)->toString();
    return new RedirectResponse($url);
  }

}
