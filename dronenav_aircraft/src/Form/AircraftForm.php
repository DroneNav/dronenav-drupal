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

    if ($aircraft_id) {
      $profiles = $this->aircraftApi->getDecelerationProfiles($aircraft_id);

      usort($profiles, static function ($a, $b) {
        return (float) $a['cruise_speed_ft_s']
          <=> (float) $b['cruise_speed_ft_s'];
      });

      $edit_profile_id = \Drupal::request()->query->get('edit_profile');
      $editing_profile = NULL;

      foreach ($profiles as $profile) {
        if ($profile['profile_id'] === $edit_profile_id) {
          $editing_profile = $profile;
          break;
        }
      }

      $form['calibration_profiles'] = [
        '#type' => 'details',
        '#title' => $this->t('Calibration Profiles'),
        '#open' => TRUE,
        '#tree' => TRUE,
      ];

      $form['calibration_profiles']['grid'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Cruise Speed (ft/s)'),
          $this->t('Deceleration (ft/s²)'),
          $this->t('Verified'),
          $this->t('Samples'),
          $this->t('Last Calibration'),
          $this->t('Operations'),
        ],
        '#empty' => $this->t('No calibration profiles defined.'),
      ];

      foreach ($profiles as $profile) {
        $edit_url = \Drupal\Core\Url::fromRoute(
          'dronenav_aircraft.edit',
          ['aircraft_id' => $aircraft_id],
          [
            'query' => [
              'edit_profile' => $profile['profile_id'],
            ],
          ]
        );

        $edit_link = \Drupal\Core\Link::fromTextAndUrl(
          $this->t('Edit'),
          $edit_url
        )->toString();

        $delete_button = [
          '#type' => 'submit',
          '#attributes' => [
            'style' => 'background: none; border: none; box-shadow: none; color: #006bb6; padding: 0; margin: 0; text-decoration: underline; cursor: pointer; font: inherit;',
          ],
          '#value' => $this->t('Delete'),
          '#name' => 'delete_profile_' . $profile['profile_id'],
          '#submit' => ['::deleteProfileSubmit'],
          '#limit_validation_errors' => [],
          '#profile_id' => $profile['profile_id'],
        ];

        $profile_id = $profile['profile_id'];

        $form['calibration_profiles']['grid'][$profile_id] = [
          'speed' => [
            '#plain_text' => $profile['cruise_speed_ft_s'],
          ],
          'deceleration' => [
            '#plain_text' => $profile['planned_deceleration_ft_s2'] ?? '—',
          ],
          'verified' => [
            '#plain_text' => !empty($profile['deceleration_verified']) ? 'Yes' : 'No',
          ],
          'samples' => [
            '#plain_text' => $profile['sample_count'] ?? 0,
          ],
          'calibrated' => [
            '#plain_text' => !empty($profile['calibrated_at'])
              ? substr($profile['calibrated_at'], 0, 16)
              : '—',
          ],
          'operations' => [
            '#type' => 'container',
            'edit' => [
              '#markup' => $edit_link . ' | ',
            ],
            'delete' => $delete_button,
          ],
        ];

      }

      if ($editing_profile !== NULL) {
        $form['calibration_profiles']['edit_profile'] = [
          '#type' => 'details',
          '#title' => $this->t('Edit Calibration Profile'),
          '#open' => TRUE,
        ];

        $form['calibration_profiles']['edit_profile']['profile_id'] = [
          '#type' => 'hidden',
          '#value' => $editing_profile['profile_id'],
        ];

        $form['calibration_profiles']['edit_profile']['cruise_speed_ft_s'] = [
          '#type' => 'number',
          '#title' => $this->t('Cruise Speed (ft/s)'),
          '#required' => TRUE,
          '#min' => 0.01,
          '#step' => 0.01,
          '#default_value' => $editing_profile['cruise_speed_ft_s'],
        ];

        $form['calibration_profiles']['edit_profile']['planned_deceleration_ft_s2'] = [
          '#type' => 'number',
          '#title' => $this->t('Initial Deceleration (ft/s²)'),
          '#min' => 0.01,
          '#step' => 0.01,
          '#default_value' => $editing_profile['planned_deceleration_ft_s2'],
          '#disabled' => $editing_profile['planned_deceleration_ft_s2'] !== NULL,
          '#description' => $this->t('Once established, deceleration is maintained by NAVProxy.'),
        ];

        $form['calibration_profiles']['edit_profile']['save'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save Profile'),
          '#submit' => ['::updateProfileSubmit'],
          '#limit_validation_errors' => [
            ['calibration_profiles', 'edit_profile'],
          ],
        ];
      }

      $form['calibration_profiles']['add_profile'] = [
        '#type' => 'details',
        '#title' => $this->t('Add Calibration Profile'),
        '#open' => FALSE,
      ];

      $form['calibration_profiles']['add_profile']['cruise_speed_ft_s'] = [
        '#type' => 'number',
        '#title' => $this->t('Cruise Speed (ft/s)'),
        '#min' => 0.01,
        '#step' => 0.01,
      ];

      $form['calibration_profiles']['add_profile']['planned_deceleration_ft_s2'] = [
        '#type' => 'number',
        '#title' => $this->t('Initial Deceleration (ft/s²)'),
        '#description' => $this->t('Optional. Once set, this value is maintained by NAVProxy.'),
        '#min' => 0.01,
        '#step' => 0.01,
      ];

      $form['calibration_profiles']['add_profile']['create'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Profile'),
        '#submit' => ['::createProfileSubmit'],
        '#limit_validation_errors' => [
          ['calibration_profiles', 'add_profile'],
        ],
      ];
    }

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
   * Deletes a calibration profile without saving the aircraft.
   */
  public function deleteProfileSubmit(
    array &$form,
    FormStateInterface $form_state
  ): void {

    $aircraft_id = \Drupal::routeMatch()->getParameter('aircraft_id');

    $button = $form_state->getTriggeringElement();
    $profile_id = $button['#profile_id'] ?? NULL;

    if (!$aircraft_id || !$profile_id) {
      $this->messenger()->addError($this->t('Unable to identify calibration profile.'));
      return;
    }

    $result = $this->aircraftApi->deleteDecelerationProfile(
      $aircraft_id,
      $profile_id
    );

    if (isset($result['error'])) {
      $this->messenger()->addError($result['error']);
    }
    else {
      $this->messenger()->addStatus($this->t('Calibration profile deleted.'));
    }

    $form_state->setRedirect(
      'dronenav_aircraft.edit',
      ['aircraft_id' => $aircraft_id]
    );

  }

  /**
   * Updates a calibration profile without saving the aircraft.
   */
  public function updateProfileSubmit(
    array &$form,
    FormStateInterface $form_state
  ): void {

    $aircraft_id = \Drupal::routeMatch()->getParameter('aircraft_id');

    $profile = $form_state->getValue([
      'calibration_profiles',
      'edit_profile',
    ], []);

    $profile_id = $profile['profile_id'] ?? NULL;
    $speed = $profile['cruise_speed_ft_s'] ?? NULL;
    $deceleration = $profile['planned_deceleration_ft_s2'] ?? NULL;

    if (!$aircraft_id || !$profile_id) {
      $this->messenger()->addError($this->t('Unable to identify calibration profile.'));
      return;
    }

    if (!is_numeric($speed) || (float) $speed <= 0) {
      $this->messenger()->addError($this->t('Enter a valid cruise speed.'));
      return;
    }

    $data = [
      'cruise_speed_ft_s' => (float) $speed,
      'updated_by' => $this->currentUser()->getAccountName(),
    ];

    // Only allow the administrator to establish an initial deceleration.
    $profiles = $this->aircraftApi->getDecelerationProfiles($aircraft_id);

    foreach ($profiles as $existing_profile) {
      if ($existing_profile['profile_id'] === $profile_id) {
        if (
          $existing_profile['planned_deceleration_ft_s2'] === NULL
          && $deceleration !== NULL
          && $deceleration !== ''
        ) {
          if (!is_numeric($deceleration) || (float) $deceleration <= 0) {
            $this->messenger()->addError($this->t('Enter a valid initial deceleration.'));
            return;
          }

          $data['planned_deceleration_ft_s2'] = (float) $deceleration;
        }

        break;
      }
    }

    $result = $this->aircraftApi->updateDecelerationProfile(
      $aircraft_id,
      $profile_id,
      $data
    );

    if (isset($result['error'])) {
      $this->messenger()->addError($result['error']);
    }
    else {
      $this->messenger()->addStatus($this->t('Calibration profile updated.'));
    }

    $form_state->setRedirect(
      'dronenav_aircraft.edit',
      ['aircraft_id' => $aircraft_id]
    );

  }

  /**
   * Creates a calibration profile without saving the aircraft.
   */
  public function createProfileSubmit(
    array &$form,
    FormStateInterface $form_state
  ): void {

    $aircraft_id = \Drupal::routeMatch()->getParameter('aircraft_id');

    $speed = $form_state->getValue([
      'calibration_profiles',
      'add_profile',
      'cruise_speed_ft_s',
    ]);

    $deceleration = $form_state->getValue([
      'calibration_profiles',
      'add_profile',
      'planned_deceleration_ft_s2',
    ]);

    if (!is_numeric($speed) || (float) $speed <= 0) {
      $this->messenger()->addError($this->t('Enter a valid cruise speed.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    if (
      $deceleration !== NULL
      && $deceleration !== ''
      && (!is_numeric($deceleration) || (float) $deceleration <= 0)
    ) {
      $this->messenger()->addError($this->t('Enter a valid initial deceleration.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $data = [
      'cruise_speed_ft_s' => (float) $speed,
      'planned_deceleration_ft_s2' => (
        $deceleration === NULL || $deceleration === ''
      ) ? NULL : (float) $deceleration,
      'updated_by' => $this->currentUser()->getAccountName(),
    ];

    $result = $this->aircraftApi->createDecelerationProfile($aircraft_id, $data);

    if (isset($result['error'])) {
      $this->messenger()->addError($result['error']);
    }
    else {
      $this->messenger()->addStatus($this->t('Calibration profile created.'));
    }

    $form_state->setRedirect(
      'dronenav_aircraft.edit',
      ['aircraft_id' => $aircraft_id]
    );

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
        (float) $form_state->getValue('maximum_speed_mph') * 22 / 15, 2),
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

