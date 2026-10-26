<?php

namespace Drupal\dronenav_aircraft\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Aviator selector for Aircraft administration.
 */
class AviatorSelectForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dronenav_aircraft_aviator_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $aviators = [];

    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['type' => 'aviator']);

    foreach ($nodes as $node) {
      $aviator_id = $node->get('field_aviator_id')->value;
      $aviator_name = $node->get('field_aviator_name')->value;

      if ($aviator_id) {
        $aviators[$aviator_id] = $aviator_name ?: $node->label();
      }
    }

    asort($aviators);

    $selected_aviator = \Drupal::request()->query->get('aviator_id', '');

    $form['aviator_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Aviator'),
      '#options' => $aviators,
      '#empty_option' => $this->t('- Select Aviator -'),
      '#default_value' => $selected_aviator,
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('View Aircraft'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $form_state->setRedirect('dronenav_aircraft.list', [], [
      'query' => [
        'aviator_id' => $form_state->getValue('aviator_id'),
      ],
    ]);
  }

}

