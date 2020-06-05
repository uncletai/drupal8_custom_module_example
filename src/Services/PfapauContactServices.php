<?php

namespace Drupal\pfapau_contact\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Exception;
use Drupal\pfapau_common\Controller\PfapauCommonMessages;

/**
 * Class PfapauContactServices.
 *
 * @package Drupal\pfapau_contact\Services
 */
class PfapauContactServices {

  const PFAPAU_CONTACT_FIELD_ADVERSE_EVENT_IDENTIFIED_YES = 'Yes';
  const PFAPAU_CONTACT_FIELD_ADVERSE_EVENT_IDENTIFIED_NO = 'No';

  /**
   * Entity Storage inter face variable.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * PfapauContactServices constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Soft delete contact log.
   */
  public function deleteLog($log_entity) {
    // 1. Check if the contact log is exists
    //    or has been deleted(field_delete_date empty means not deleted).
    if (!$log_entity
      || ($log_entity && !empty($log_entity->field_delete_date->value))) {
      throw new NotFoundHttpException(t(PfapauCommonMessages::PFAPAU_NOT_FOUND));
    }

    // 2. Contact log cannot be deleted when 'Adverse event identified' is 'Yes'.
    $identified = $log_entity->field_adverse_event_identified->value;
    if ($identified == self::PFAPAU_CONTACT_FIELD_ADVERSE_EVENT_IDENTIFIED_YES) {
      throw new Exception(t(PfapauCommonMessages::PFAPAU_CONTACT_IDENTIFIED_DEL_ERROR));
    }

    // 3. Contact log cannot be deleted if the create_date or contact_date is the current month.
    $is_not_current = $this->checkTimeExpire($log_entity, 'delete');
    if ($is_not_current) {
      throw new Exception(t(PfapauCommonMessages::PFAPAU_CONTACT_NOT_CURRENT_MONTH_DEL_ERROR));
    }

    // 4. Soft delete contact log(the data exported by the report needs to include deleted data).
    $current_user = \Drupal::currentUser()->id();
    $log_entity->set('field_delete_date', date('Y-m-d'));
    $log_entity->set('field_delete_by', $current_user);

    if ($log_entity->save()) {
      drupal_set_message(t(PfapauCommonMessages::PFAPAU_CONTACT_DELETE_SUCCESS_MESSAGE));
    }

  }

  /**
   * Function to check if the 'created date' or 'contact date' is the current month.
   */
  public function checkTimeExpire(Object $entity, $action = 'edit') {
    $create_date = $entity->created->getString();
    $create_month_year = \Drupal::service('date.formatter')->format($create_date, 'custom', 'm/Y');
    $contact_date = $entity->field_contact_date->value;
    $contact_month_year = substr($contact_date, 3);
    $current_month_year = \Drupal::service('date.formatter')->format(time(), 'custom', 'm/Y');
    $identified = $entity->field_adverse_event_identified->value;

    // 1. Check if created date and contact both are current month when identified is 'No' in contact log edit form,
    //    if yes, show 'Delete' button.
    if ($action == 'edit'
      && $identified == self::PFAPAU_CONTACT_FIELD_ADVERSE_EVENT_IDENTIFIED_NO
      && $create_month_year == $current_month_year
      && $contact_month_year == $current_month_year) {
      return TRUE;
    }

    // 2. Check if created date or contact date is current month in contact log edit form,
    //    if not, return TRUE to disable fields.
    if ($action == 'disable'
      && ($create_month_year != $current_month_year || $contact_month_year != $current_month_year)) {
      return TRUE;
    }

    // 3. Check if created date and contact date is current month in contact log edit form,
    //    if yes, return TRUE to delete.
    if ($action == 'delete'
      && ($create_month_year != $current_month_year || $contact_month_year != $current_month_year)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get 'Type of contact' taxonomy terms with tid as options.
   */
  public function getTaxonomyTermsWithTid($taxonomy_machine_name) {
    $types = [];
    $terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadTree($taxonomy_machine_name, 0, 1);
    if (!empty($terms)) {
      foreach ($terms as $item) {
        $types[$item->tid] = $item->name;
      }
    }

    return $types;
  }

  /**
   * Validate variable has special char or not.
   */
  public function validateHasSpecialChar($variable) {
    $value = $this->getValiableValue($variable);
    return $this->pregSpecialChars($value);
  }

  /**
   * Get field value.
   */
  public function getValiableValue($variable) {
    $value = '';
    // For array type(contact log add form field).
    if (is_array($variable) && isset($variable[0]['value'])) {
      $value = $variable[0]['value'];
    }
    // For string type(contact log edit field value).
    if (is_string($variable)) {
      $value = $variable;
    }

    return $value;
  }

  /**
   * Function to preg special chars.
   */
  public function pregSpecialChars($valid_string) {
    $special_char_pattern = PfapauCommonMessages::PFAPAU_SPECHAR_CHARS_STRING;
    return preg_match($special_char_pattern, $valid_string);
  }

  /**
   * Validate date.
   */
  public function dateFormatValidate($date) {
    $date_value = $this->getValiableValue($date);
    $time = strtotime(str_replace('/', '-', $date_value));

    return $time;
  }

}
