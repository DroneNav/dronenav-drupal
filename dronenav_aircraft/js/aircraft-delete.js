
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.dronenavAircraftDelete = {
    attach(context) {
      once('dronenav-aircraft-delete', '.dronenav-aircraft-delete', context)
        .forEach(function (button) {
          button.addEventListener('click', function (event) {
            if (!window.confirm(Drupal.t('Are you sure you want to delete this aircraft?'))) {
              event.preventDefault();
            }
          });
        });
    }
  };

})(Drupal, once);

