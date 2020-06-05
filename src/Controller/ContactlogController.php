<?php

namespace Drupal\pfapau_contact\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\pfapau_contact\Services\PfapauContactServices;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\block\Entity\Block;
use Exception;
use Drupal\eck\EckEntityInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ContactlogController.
 *
 * @package Drupal\pfapau_contact\Controller
 */
class ContactlogController extends ControllerBase {

  // Contact log.
  const PFAPAU_CONTACT_CREATE_SUCCESS_MESSAGE = 'Contact log Added successfully.';
  const PFAPAU_CONTACT_UPDATE_SUCCESS_MESSAGE = 'Contact log updated successfully.';
  const PFAPAU_CONTACT_DELETE_SUCCESS_MESSAGE = 'Contact log deleted successfully.';
  const PFAPAU_CONTACT_HAS_BEEN_DELETED_ERROR = 'Contact log has been deleted.';
  const PFAPAU_CONTACT_IDENTIFIED_DEL_ERROR = 'The AE identified is yes, it can not be deleted.';
  const PFAPAU_CONTACT_NOT_CURRENT_MONTH_DEL_ERROR = 'The created date or the contact date of the log is not the current month, it cannot be deleted.';
  const PFAPAU_NOT_FOUND = 'Page not found.';
  const PFAPAU_DATE_FORMAT_MESSAGE = 'Please enter a valid date of birth.';

  /**
   * Contact service.
   *
   * @var Drupal\pfapau_contact\Services\PfapauContactServices
   */
  protected $contactServices;

  /**
   * Construct.
   *
   * @param Drupal\pfapau_contact\Services\PfapauContactServices $contactServices
   *   Contact service.
   */
  public function __construct(PfapauContactServices $contactServices) {
    $this->contactServices = $contactServices;
  }

  /**
   * Contact log(s) manage page.
   *
   * @return array
   *   Array.
   */
  public function list() {
    // Render 'Contact logs list' views block.
    $block = Block::load('views_block__contact_logs_list_block_1');
    $render = \Drupal::entityTypeManager()->getViewBuilder('block')->view($block);
    return [
      '#title' => t('Manage contact'),
      '#markup' => \Drupal::service('renderer')->render($render),
    ];
  }

  /**
   * Delete contact log.
   *
   * @param \Drupal\eck\EckEntityInterface $log_entity
   *   Contact log entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Soft delete the contact log and output a json result.
   */
  public function deleteLog(EckEntityInterface $log_entity) {
    try {
      $this->contactServices->deleteLog($log_entity);
      return new JsonResponse(['stat' => 'ok']);
    }
    catch (Exception $ex) {
      return new JsonResponse([
        'stat' => 'error',
        'message' => t($ex->getMessage())
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $contact_service = $container->get('pfapau_services.pfapau_contact');
    return new static($contact_service);
  }

}
