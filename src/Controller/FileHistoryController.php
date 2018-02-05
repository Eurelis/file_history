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
   * Set File for Use.
   *
   * @param \Drupal\file\FileInterface $file
   *   File object.
   * @param string $config_name
   *   Config Id to load.
   * @param string $destination
   *   Destination of return.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection.
   */
  public function useFile(FileInterface $file, $config_name, $destination) {

    // Set file as active.
    $config = \Drupal::service('config.factory')->getEditable('file_history.' . $config_name);
    $config->set('activ_file', $file->id())->save();

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
   * Relaod File.
   *
   * @param \Drupal\file\FileInterface $file
   *   File object.
   * @param string $config_name
   *   Config Id to load.
   * @param string $destination
   *   Destination of return.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection.
   */
  public function reloadFile(FileInterface $file, $config_name, $destination) {

    // Set file as active.
    $config = \Drupal::service('config.factory')->getEditable('file_history.' . $config_name);
    $config->set('activ_file', $file->id())->save();

    return $this->returnToPage($destination);
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
