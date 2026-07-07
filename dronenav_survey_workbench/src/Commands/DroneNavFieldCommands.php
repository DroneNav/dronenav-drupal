<?php

namespace Drupal\dronenav_survey_workbench\Commands;

use Drush\Commands\DrushCommands;

class DroneNavFieldCommands extends DrushCommands {

  /**
   * Lists fields for a node content type.
   *
   * @command dronenav:fields
   * @aliases dnf
   *
   * @param string $bundle
   *   Content type machine name.
   */
  public function fields(string $bundle): void {
    $fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', $bundle);

    if (empty($fields)) {
      $this->output()->writeln("No fields found for content type: {$bundle}");
      return;
    }

    $this->output()->writeln("Content Type: {$bundle}");
    $this->output()->writeln(str_repeat('-', 100));
    $this->output()->writeln(sprintf(
      "%-35s %-30s %-25s %-10s",
      'Machine Name',
      'Label',
      'Type',
      'Required'
    ));
    $this->output()->writeln(str_repeat('-', 100));

    foreach ($fields as $name => $field) {
      $this->output()->writeln(sprintf(
        "%-35s %-30s %-25s %-10s",
        $name,
        (string) $field->getLabel(),
        $field->getType(),
        $field->isRequired() ? 'Yes' : 'No'
      ));
    }
  }

}

