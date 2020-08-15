/**
 * @file entity_browser.entity_reference.js
 *
 * Defines the behavior of the entity reference widget that utilizes entity
 * browser.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Registers behaviours related to entity reference field widget.
   */
  Drupal.behaviors.entityBrowserEntityReference = {
    attach: function (context) {
      $(context).find('.field--widget-entity-browser-entity-reference').each(function () {
        $(this).find('.entities-list.sortable').sortable({
          stop: Drupal.entityBrowserEntityReference.entitiesReordered
        });
      });

      $(context).find(".field--widget-entity-browser-table").each(function () {
        var $entitiesList = $(this).find('.entities-list');
        $(this).find('table.entities-list .tabledrag-handle')
          .once('entity-browser-table-widget-drag')
          .on('mouseup pointerup', function () {
            Drupal.entityBrowserEntityReference.updateTargetId($entitiesList);
          });
      });

      $(context).find('[data-entity-browser-entities-list] .remove-button').each(function () {
        $(this).once('entity-browser-remove').on('mousedown', function(e) {
          var $currentItems = $(this).parents('[data-entity-browser-entities-list]:first');
          $(this).parents('.item-container:first').fadeOut(360, function() {
            $(this).remove();
            Drupal.entityBrowserEntityReference.updateTargetId($currentItems);
          });
        });
        // Prevent remove button from triggering a form submit.
        $(this).once('entity-browser-remove-mouseup').on('click', function(e) {
          e.preventDefault();
        });
      });

      // The AJAX callback will give us a flag when we need to re-open the
      // browser, most likely due to a "Replace" button being clicked.
      if (typeof drupalSettings.entity_browser_reopen_browser !== 'undefined' &&  drupalSettings.entity_browser_reopen_browser) {
        var data_drupal_selector = '[data-drupal-selector^="edit-' + drupalSettings.entity_browser_reopen_browser.replace(/_/g, '-') + '-entity-browser-entity-browser-' + '"][data-uuid]';
        var $launch_browser_element = $(context).find(data_drupal_selector);
        if ($launch_browser_element.attr('data-uuid') in drupalSettings.entity_browser && !drupalSettings.entity_browser[$launch_browser_element.attr('data-uuid')].auto_open) {
          $launch_browser_element.click();
        }
        // In case this is inside a fieldset closed by default, open it so the
        // user doesn't need to guess the browser is open but hidden there.
        var $fieldset_summary = $launch_browser_element.closest('details').find('summary');
        if ($fieldset_summary.length && $fieldset_summary.attr('aria-expanded') === 'false') {
          $fieldset_summary.click();
        }
      }
    }
  };

  Drupal.entityBrowserEntityReference = {};

  /**
   * Reacts on sorting of the entities.
   *
   * @param {object} event
   *   Event object.
   * @param {object} ui
   *   Object with detailed information about the sort event.
   */
  Drupal.entityBrowserEntityReference.entitiesReordered = function (event, ui) {
    Drupal.entityBrowserEntityReference.updateTargetId($(this));
  };

  /**
   * Updates the 'target_id' element.
   *
   * @param {object} $currentItems
   *   Object with '.entities-list.sortable' element.
   */
  Drupal.entityBrowserEntityReference.updateTargetId = function ($currentItems) {
    var items = $currentItems.find('.item-container');
    var ids = [];
    for (var i = 0; i < items.length; i++) {
      ids[i] = $(items[i]).attr('data-entity-id');
      // If using weight field, update it.
      $(items[i]).find('input[name*="[_weight]"]').val(i);
    }
    var $target_id_element = $currentItems.parent().find('input[type*=hidden][name*="[target_id]"]');
    $target_id_element.val(ids.join(' '));

    // Trigger ajax submission to restore entity browser form element.
    var cardinality = parseInt($target_id_element.attr('data-cardinality'));
    if (ids.length < cardinality && $target_id_element.attr('data-entity-browser-visible') === "0") {
      $target_id_element.trigger('entity_browser_value_updated');
    }
  }

}(jQuery, Drupal));
