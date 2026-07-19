<?php

namespace Drupal\dronenav_flight_band\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dronenav_flight_band\Service\FlightBandApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\dronenav_api\Service\ReferenceDataService;

/**
 * Flight Band add/edit form.
 */
class FlightBandForm extends FormBase {

  /**
   * Flight Band API service.
   *
   * @var \Drupal\dronenav_flight_band\Service\FlightBandApi
   */
  protected FlightBandApi $flightBandApi;

  /**
   * Reference data service.
   *
   * @var \Drupal\dronenav_api\Service\ReferenceDataService
   */
  protected ReferenceDataService $referenceDataService;

  /**
   * Constructor.
   */
  public function __construct(
    FlightBandApi $flight_band_api,
    ReferenceDataService $reference_data_service,
  ) {
    $this->flightBandApi = $flight_band_api;
    $this->referenceDataService = $reference_data_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_flight_band.api'),
      $container->get('dronenav_api.reference_data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dronenav_flight_band_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uuid = NULL): array {

    $form_state->set('uuid', $uuid);

    $band = [];

    if ($uuid) {
      $band = $this->flightBandApi->getFlightBand($uuid);
    }

    $form['uuid'] = [
      '#type' => 'hidden',
      '#value' => $uuid,
    ];

    $form['flight_class'] = [
      '#type' => 'select',
      '#title' => $this->t('Flight Class'),
      '#required' => TRUE,
      '#options' => $this->referenceDataService->getFlightClasses(),
      '#default_value' => $band['flight_class'] ?? '',
    ];

    $form['band_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Band Name'),
      '#required' => TRUE,
      '#default_value' => $band['band_name'] ?? '',
    ];

    $form['min_agl_ft'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum AGL Feet'),
      '#required' => TRUE,
      '#default_value' => $band['min_agl_ft'] ?? 0,
    ];

    $form['max_agl_ft'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum AGL Feet'),
      '#required' => TRUE,
      '#default_value' => $band['max_agl_ft'] ?? 500,
    ];

    $form['days'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Days of Week'),
      '#options' => [
        0 => $this->t('Sunday'),
        1 => $this->t('Monday'),
        2 => $this->t('Tuesday'),
        3 => $this->t('Wednesday'),
        4 => $this->t('Thursday'),
        5 => $this->t('Friday'),
        6 => $this->t('Saturday'),
      ],
      '#default_value' => $band['days'] ?? [0, 1, 2, 3, 4, 5, 6],
    ];

    $form['start_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start Time'),
      '#required' => TRUE,
      '#default_value' => $band['start_time'] ?? '00:00',
      '#description' => $this->t('Use HH:MM format, for example 08:00.'),
      '#attributes' => [
        'placeholder' => '00:00',
        'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$',
      ],
    ];

    $form['end_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End Time'),
      '#required' => TRUE,
      '#default_value' => $band['end_time'] ?? '23:59',
      '#description' => $this->t('Use HH:MM format, for example 18:00.'),
      '#attributes' => [
        'placeholder' => '23:59',
        'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$',
      ],
    ];

    $form['operational_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Operational Status'),
      '#required' => TRUE,
      '#options' => [
        'active' => $this->t('Active'),
        'inactive' => $this->t('Inactive'),
      ],
      '#default_value' => $band['operational_status'] ?? 'active',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Flight Band'),
      '#button_type' => 'primary',
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $start_time = $form_state->getValue('start_time');
    $end_time = $form_state->getValue('end_time');

    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start_time)) {
      $form_state->setErrorByName('start_time', $this->t('Start Time must be in HH:MM format.'));
    }

    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) {
      $form_state->setErrorByName('end_time', $this->t('End Time must be in HH:MM format.'));
    }

    if ($start_time >= $end_time) {
      $form_state->setErrorByName('end_time', $this->t('End Time must be later than Start Time.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $uuid = $form_state->getValue('uuid');

    $days = [];

    foreach ($form_state->getValue('days') as $value) {
      if ($value !== NULL && $value !== '' && $value !== FALSE) {
        $days[] = (int) $value;
      }
    }

    $days = array_values(array_unique($days));
    sort($days);

    $data = [
      'flight_class' => $form_state->getValue('flight_class'),
      'band_name' => $form_state->getValue('band_name'),
      'min_agl_ft' => (int) $form_state->getValue('min_agl_ft'),
      'max_agl_ft' => (int) $form_state->getValue('max_agl_ft'),
      'days' => $days,
      'start_time' => $form_state->getValue('start_time'),
      'end_time' => $form_state->getValue('end_time'),
      'operational_status' => $form_state->getValue('operational_status'),
    ];

    if ($uuid) {
      $data['updated_by'] = $this->currentUser()->getAccountName();
    }
    else {
      $data['created_by'] = $this->currentUser()->getAccountName();
    }

\Drupal::logger('dronenav_flight_band')->notice('Flight Band update payload: @payload', [
  '@payload' => json_encode($data),
]);

    if ($uuid) {
      $result = $this->flightBandApi->updateFlightBand($uuid, $data);

      if (!empty($result)) {
        $this->messenger()->addStatus($this->t('Flight Band updated.'));
      }
      else {
        $this->messenger()->addError($this->t('Flight Band update failed. Check Drupal logs.'));
      }
    }
    else {
      $result = $this->flightBandApi->createFlightBand($data);

      if (!empty($result)) {
        $this->messenger()->addStatus($this->t('Flight Band created.'));
      }
      else {
        $this->messenger()->addError($this->t('Flight Band creation failed. Check Drupal logs.'));
      }
    }

    $form_state->setRedirect('dronenav_flight_band.list');
  }

}

