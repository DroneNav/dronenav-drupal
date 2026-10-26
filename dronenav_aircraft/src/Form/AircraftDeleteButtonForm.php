<?php

namespace Drupal\dronenav_aircraft\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dronenav_aircraft\Service\AircraftApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete button form for an aircraft listing row.
 */
class AircraftDeleteButtonForm extends FormBase {

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
    return 'dronenav_aircraft_delete_button_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $aircraft_id = NULL,
    $aviator_id = NULL
  ): array {

    $form_state->set('aircraft_id', $aircraft_id);
    $form_state->set('aviator_id', $aviator_id);

    $form['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#attributes' => [
        'class' => [
          'dronenav-aircraft-delete',
          'button--link',
        ],
      ],
    ];

    $form['#attached']['library'][] = 'dronenav_aircraft/aircraft_delete';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $aircraft_id = $form_state->get('aircraft_id');
    $aviator_id = $form_state->get('aviator_id');

    if ($this->aircraftApi->deleteAircraft($aircraft_id)) {
      $this->messenger()->addStatus($this->t('Aircraft deleted.'));
    }
    else {
      $this->messenger()->addError(
        $this->t('Aircraft could not be deleted. Check Drupal logs.')
      );
    }

    $form_state->setRedirect('dronenav_aircraft.list', [], [
      'query' => [
        'aviator_id' => $aviator_id,
      ],
    ]);
  }

}

