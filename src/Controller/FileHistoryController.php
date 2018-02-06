<?php

namespace Drupal\file_history\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\FileInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Url;

/**
 * Class RememberFilesController.
 */
class FileHistoryController extends ControllerBase {

  /**
   * Select File.
   *
   * @param \Drupal\file\FileInterface $file
   *   File object.
   * @param string $config_name
   *   Config Id to load.
   * @param int $multiple
   *   Boolean.
   * @param string $destination
   *   Destination of return.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection.
   */
  public function selectFile(FileInterface $file, $config_name, $multiple, $destination) {

    // Set file as active.
    $config = \Drupal::service('config.factory')->getEditable('file_history.' . $config_name);

    $activ = $config->get('activ_file', []);

    if ($multiple == 1) {
      $activ[] = $file->id();
    }
    else {
      $activ = [$file->id()];
    }

    $config->set('activ_file', $activ)->save();

    return $this->returnToPage($destination);
  }

  /**
   * Unselect File.
   *
   * @param \Drupal\file\FileInterface $file
   *   File object.
   * @param string $config_name
   *   Config Id to load.
   * @param int $multiple
   *   Boolean.
   * @param string $destination
   *   Destination of return.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection.
   */
  public function unselectFile(FileInterface $file, $config_name, $multiple, $destination) {

    // Set file as active.
    $config = \Drupal::service('config.factory')->getEditable('file_history.' . $config_name);

    $activ = $config->get('activ_file', []);

    $key = array_search($file->id(), $activ);
    if ($key !== FALSE) {
      unset($activ[$key]);
      $config->set('activ_file', $activ)->save();
    }

    return $this->returnToPage($destination);
  }

  /**
   * Delete File.
   *
   * @param \Drupal\file\FileInterface $file
   *   File object.
   * @param string $destination
   *   Destination of return.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteFile(FileInterface $file, $destination) {
    $file->delete();
    return $this->returnToPage($destination);
  }

  /**
   * Download non public files.
   *
   * @param \Drupal\file\FileInterface $file
   *   File object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   File content
   */
  public function downloadLogFile(FileInterface $file) {

    $real_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $fileContent = file_get_contents($real_path);

    $response = new Response($fileContent);

    $disposition = $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getFilename()
    );

    $response->headers->set('Content-Disposition', $disposition);

    return $response;
  }

  /**
   * Return to parent page.
   *
   * @param string $destination
   *   Destination of return.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection.
   */
  private function returnToPage($destination) {
    $url = Url::fromRoute($destination)->toString();
    return new RedirectResponse($url);
  }

}
