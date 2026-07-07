<?php

namespace Drupal\dronenav_flight_band\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dronenav_flight_band\Service\FlightBandApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for deleting a Flight Band.
 */
class FlightBandDeleteForm extends ConfirmFormBase {

  /**
   * Flight Band API service.
   *
   * @var \Drupal\dronenav_flight_band\Service\FlightBandApi
   */
  protected FlightBandApi $flightBandApi;

  /**
   * Flight Band UUID.
   *
   * @var string|null
   */
  protected ?string $uuid = NULL;

  protected array $band = [];

  /**
   * Constructor.
   */
  public function __construct(FlightBandApi $flight_band_api) {
    $this->flightBandApi = $flight_band_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dronenav_flight_band.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dronenav_flight_band_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    $name = $this->band['band_name'] ?? $this->uuid ?? '';

    return $this->t('Delete Flight Band "@name"?', [
      '@name' => $name,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('dronenav_flight_band.list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return $this->t('Delete');
  }

  /**
   * Builds the confirmation form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $uuid = NULL): array {
    $this->uuid = $uuid;

    if ($uuid) {
      $this->band = $this->flightBandApi->getFlightBand($uuid);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->uuid) {
      $this->flightBandApi->deleteFlightBand($this->uuid);
      $this->messenger()->addStatus($this->t('Flight Band deleted.'));
    }

    $form_state->setRedirect('dronenav_flight_band.list');
  }

}

