(function (Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.flightPlanStatusPolling = {
    attach(context) {
      const rows = once(
        'flight-plan-status-polling',
        '.flight-plan-row',
        context
      );

      if (rows.length === 0) {
        return;
      }

      const statusUrl =
        drupalSettings.dronenavFlightPlan?.statusUrl;

      if (!statusUrl) {
        return;
      }

      const updateStatuses = async () => {
        try {
          const response = await fetch(statusUrl, {
            headers: {
              Accept: 'application/json',
            },
            credentials: 'same-origin',
            cache: 'no-store',
          });

          if (!response.ok) {
            return;
          }

          const statuses = await response.json();

          document
            .querySelectorAll('.flight-plan-row')
            .forEach((row) => {
              const nodeId = row.dataset.nodeId;
              const statusCell = row.querySelector(
                '.flight-plan-status'
              );

              if (
                statusCell &&
                statuses[nodeId] &&
                typeof statuses[nodeId].status === 'string'
              ) {
                statusCell.textContent =
                  statuses[nodeId].status;
              }
            });
        }
        catch (error) {
          console.error(
            'Unable to retrieve Flight Plan statuses.',
            error
          );
        }
      };

      updateStatuses();
      window.setInterval(updateStatuses, 10000);
    },
  };
})(Drupal, drupalSettings, once);

