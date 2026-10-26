<?php

namespace Drupal\dronenav_aircraft\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dronenav_aircraft\Service\AircraftApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Aircraft add/edit form.
 */
class AircraftForm extends FormBase {

  protected AircraftApi $aircraftApi;

  /**
   * Constructor.
   */
  public function __construct(AircraftApi $aircraft_api) {
    $this->aircraftApi = $aircraft_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_aircraft.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dronenav_aircraft_form';
  }

  /**
   * Returns taxonomy options keyed by lowercase term name.
   */
  protected function getTaxonomyOptions(string $vocabulary): array {

    $options = [];

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $vocabulary]);

    foreach ($terms as $term) {
      $options[strtolower($term->label())] = $term->label();
    }

    asort($options);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $aircraft_id = NULL
  ): array {

    $aircraft = [];

    if ($aircraft_id) {
      $aircraft = $this->aircraftApi->getAircraft($aircraft_id);
    }

    $aviator_id = $aircraft['aviator_id']
      ?? \Drupal::request()->query->get('aviator_id', '');

    $form['aircraft_id'] = [
      '#type' => 'hidden',
      '#value' => $aircraft_id,
    ];

    $form['aviator_id'] = [
      '#type' => 'hidden',
      '#value' => $aviator_id,
    ];

    $form['aircraft_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Aircraft Name'),
      '#required' => TRUE,
      '#default_value' => $aircraft['aircraft_name'] ?? '',
    ];

    $form['manufacturer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Manufacturer'),
      '#required' => TRUE,
      '#default_value' => $aircraft['manufacturer'] ?? '',
    ];

    $form['model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model'),
      '#required' => TRUE,
      '#default_value' => $aircraft['model'] ?? '',
    ];

    $form['aircraft_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Aircraft Type'),
      '#required' => TRUE,
      '#options' => $this->getTaxonomyOptions('aircraft_type'),
      '#default_value' => strtolower($aircraft['aircraft_type'] ?? ''),
    ];

    foreach ([
      'length_cm' => 'Length (cm)',
      'width_cm' => 'Width (cm)',
      'height_cm' => 'Height (cm)',
      'maximum_takeoff_weight_kg' => 'Maximum Takeoff Weight (kg)',
    ] as $field => $label) {
      $form[$field] = [
        '#type' => 'number',
        '#title' => $this->t($label),
        '#required' => TRUE,
        '#min' => 0,
        '#step' => 0.01,
        '#default_value' => $aircraft[$field] ?? NULL,
      ];
    }

    $form['maximum_speed_mph'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Speed (mph)'),
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 0.01,
      '#default_value' => isset($aircraft['maximum_speed_ft_s'])
        ? round((float) $aircraft['maximum_speed_ft_s'] * 15 / 22, 2)
        : NULL,
    ];

    $form['operational_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Operational Status'),
      '#required' => TRUE,
      '#options' => $this->getTaxonomyOptions('aircraft_status'),
      '#default_value' => strtolower($aircraft['operational_status'] ?? 'active'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Aircraft'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $aircraft_id = $form_state->getValue('aircraft_id');
    $aviator_id = $form_state->getValue('aviator_id');

    $data = [
      'aviator_id' => $aviator_id,
      'aircraft_name' => $form_state->getValue('aircraft_name'),
      'manufacturer' => $form_state->getValue('manufacturer'),
      'model' => $form_state->getValue('model'),
      'aircraft_type' => strtolower($form_state->getValue('aircraft_type')),
      'length_cm' => (float) $form_state->getValue('length_cm'),
      'width_cm' => (float) $form_state->getValue('width_cm'),
      'height_cm' => (float) $form_state->getValue('height_cm'),
      'maximum_takeoff_weight_kg' => (float) $form_state->getValue('maximum_takeoff_weight_kg'),
      'maximum_speed_ft_s' => round(
        (float) $form_state->getValue('maximum_speed_mph') * 22 / 15,
        2
      ),
      'operational_status' => strtolower($form_state->getValue('operational_status')),
    ];

    if ($aircraft_id) {
      unset($data['aviator_id']);
      $data['updated_by'] = $this->currentUser()->getAccountName();
      $result = $this->aircraftApi->updateAircraft($aircraft_id, $data);
    }
    else {
      $data['created_by'] = $this->currentUser()->getAccountName();
      $result = $this->aircraftApi->createAircraft($data);
    }

    if (!empty($result)) {
      $this->messenger()->addStatus(
        $this->t('Aircraft saved successfully.')
      );
    }
    else {
      $this->messenger()->addError(
        $this->t('Aircraft save failed. Check Drupal logs.')
      );
    }

    $form_state->setRedirect('dronenav_aircraft.list', [], [
      'query' => ['aviator_id' => $aviator_id],
    ]);
  }

}

