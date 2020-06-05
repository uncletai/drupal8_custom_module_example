<?php

namespace Drupal\pfapau_contact\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\user\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\pfapau_common\Controller\PfapauCommonMessages;
use Drupal\pfapau_contact\Services\PfapauContactServices;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add contact-log form.
 */
class ContactLogForm extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contact_log_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $log_id = NULL) {
    // Contact add form.
    if (!$log_id) {
      $new_log = \Drupal::entityManager()->getStorage('contact_logs')->create(
        [
          'type' => 'contact_logs',
        ]
      );
      $form_display = EntityFormDisplay::collectRenderDisplay($new_log, 'default');
      $form_display->buildForm($new_log, $form, $form_state);

      $form['field_contact_date']['#prefix'] = '<div class="form-items">';
      // Alter actions in add form.
      $form['actions'] = [
        '#weight' => 99,
        '#prefix' => '<div class="form-actions">',
        '#suffix' => '</div>',
      ];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => t('Add contact log'),
      ];
      // Remove empty langcode field.
      unset($form['langcode']);
    }
    // Contact edit form.
    else {
      $log_entity = \Drupal::entityTypeManager()->getStorage('contact_logs')->load($log_id);
      // Check the contact log exists or has been deleted.
      if ($log_entity && empty($log_entity->field_delete_date->value)) {
        $form_state->set('log_entity', $log_entity);
        // Get entity fields.
        $author_uid = $log_entity->uid->getString();
        $author = User::load($author_uid);
        $create_date = $log_entity->created->getString();

        $form['field_created_date'] = [
          '#type' => 'textfield',
          '#title' => t('Date created'),
          '#value' => date('d/m/Y', $create_date),
          '#disabled' => TRUE,
          '#prefix' => '<div class="form-items">'
        ];

        $form['field_created_by'] = [
          '#type' => 'textfield',
          '#title' => t('Created by'),
          '#default_value' => $author->name->value,
          '#disabled' => TRUE,
        ];

        $form['field_contact_date'] = [
          '#type' => 'textfield',
          '#title' => t('Contact date'),
          '#required' => TRUE,
          '#default_value' => $log_entity->field_contact_date->value,
        ];

        $contact_service = \Drupal::service('pfapau_services.pfapau_contact');
        $terms = $contact_service->getTaxonomyTermsWithTid('type_of_contact');
        $form['field_type_of_contact'] = [
          '#type' => 'select',
          '#title' => t('Type of contact'),
          '#required' => TRUE,
          '#default_value' => $log_entity->field_type_of_contact->target_id,
          '#options' => $terms,
        ];

        $form['field_contact_name'] = [
          '#type' => 'textfield',
          '#title' => t('Contact name'),
          '#required' => TRUE,
          '#default_value' => $log_entity->field_contact_name->value,
          '#maxlength' => 50,
        ];

        $form['field_contact_note'] = [
          '#type' => 'textarea',
          '#title' => t('Contact note'),
          '#required' => TRUE,
          '#default_value' => $log_entity->field_contact_note->value,
        ];

        $options = $log_entity->field_adverse_event_identified->getSetting('allowed_values');
        $form['field_adverse_event_identified'] = [
          '#type' => 'select',
          '#title' => t('Adverse event identified'),
          '#required' => TRUE,
          '#default_value' => $log_entity->field_adverse_event_identified->value,
          '#options' => $options,
        ];

        $form['field_ae_receipt_no'] = [
          '#type' => 'textfield',
          '#title' => t('AE receipt no'),
          '#default_value' => $log_entity->field_ae_receipt_no->value,
          '#maxlength' => 50,
        ];

        // Add actions in edit form.
        $form['actions'] = [
          '#prefix' => '<div class="form-actions">',
          '#suffix' => '</div>'
        ];
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => t('Update contact log'),
        ];
        if ($contact_service->checkTimeExpire($log_entity, 'edit')) {
          $form['actions']['delete'] = [
            '#markup' => '<a id="btn-delete-' . $log_id . '" class="button btn-delete" data-ajax="/contact_log/' . $log_id . '/delete">' . t('Delete contact log') . '</a>',
          ];
        }
        $form['actions']['cancel'] = [
          '#markup' => '<a class="enrol-cancel-button" href="/xeljanz/contacts">' . t('Cancel') . '</a>',
        ];

        // Logic of field disable.
        if ($contact_service->checkTimeExpire($log_entity, 'disable')) {
          $form['field_contact_date']['#disabled'] = TRUE;
          $form['field_contact_name']['#disabled'] = TRUE;
          $form['field_contact_note']['#disabled'] = TRUE;
          $form['field_type_of_contact']['#disabled'] = TRUE;
        }
      }
      else {
        throw new NotFoundHttpException();
      }
    }

    $form['field_ae_receipt_no']['#suffix'] = '<div class="form-note"><p><sup>†</sup>' . t('To report adverse events, contact Pfizer Drug Safety
    by phone 1800 734 260, fax 1800 034 314 or email <a href="mailto:AUS.AEReporting@pfizer.com">AUS.AEReporting@pfizer.com</a>') . '</p></div></div>';
    $form['actions']['cancel'] = [
      '#markup' => '<a class="enrol-cancel-button" href="/xeljanz/contacts">' . t('Cancel') . '</a>',
    ];
    $form['#attributes']['novalidate'] = 'novalidate';

    return $form;
  }

  /**
   * Valid form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate special chars.
    $values = $form_state->getValues();
    foreach ($values as $key => $field) {
      $has_special_char = $this->contactServices->validateHasSpecialChar($field);
      if ($has_special_char) {
        $form_state->setErrorByName($key, t(PfapauCommonMessages::MESSAGE_NOT_ALLOW_SPECIAL_CHARS_ERROR));
      }
    }
    // Validate date format.
    $contact_date = $form_state->getValue('field_contact_date');
    if (!$this->contactServices->dateFormatValidate($contact_date)) {
      $form_state->setErrorByName('field_contact_date', t(PfapauCommonMessages::PFAPAU_DATE_FORMAT_MESSAGE));
    }
  }

  /**
   * Submit for contact log.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->get('log_entity')) {
      // For contact log add submit.
      $account = \Drupal::currentUser();
      $log_values = [
        'type' => 'contact_logs',
        'created' => \Drupal::time()->getRequestTime(),
        'uid' => $account->id(),
        'field_adverse_event_identified' => $form_state->getValue('field_adverse_event_identified'),
        'field_ae_receipt_no' => $form_state->getValue('field_ae_receipt_no'),
        'field_contact_date' => $form_state->getValue('field_contact_date'),
        'field_contact_name' => $form_state->getValue('field_contact_name'),
        'field_contact_note' => $form_state->getValue('field_contact_note'),
        'field_type_of_contact' => $form_state->getValue('field_type_of_contact'),
      ];
      $new_log = \Drupal::entityManager()->getStorage('contact_logs')->create($log_values);
      $create = $new_log->save();
      if ($create) {
        drupal_set_message(t(PfapauCommonMessages::PFAPAU_CONTACT_CREATE_SUCCESS_MESSAGE));
      }
    }
    else {
      // For contact log  edit submit.
      $log_entity = $form_state->get('log_entity');
      $log_entity->field_adverse_event_identified = $form_state->getValue('field_adverse_event_identified');
      $log_entity->field_type_of_contact = $form_state->getValue('field_type_of_contact');
      $log_entity->field_ae_receipt_no = $form_state->getValue('field_ae_receipt_no');
      $log_entity->field_contact_date = $form_state->getValue('field_contact_date');
      $log_entity->field_contact_name = $form_state->getValue('field_contact_name');
      $log_entity->field_contact_note = $form_state->getValue('field_contact_note');
      $update = $log_entity->save();
      if ($update) {
        drupal_set_message(t(PfapauCommonMessages::PFAPAU_CONTACT_UPDATE_SUCCESS_MESSAGE));
      }
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
