/**
 * @file entity_browser.table.js
 *
 * Allows the table rows to be sortable.
 */

(function ($, Drupal) {
    'use strict';
    document.querySelector('.table--widget-entity_reference_browser_table_widget').querySelector('tbody').classList += ' entities-list sortable ui-sortable';
    Drupal.behaviors.entityBrowserEntityReferenceTable = {
        attach: function (context) {
            $(context).find('.table--widget-entity_reference_browser_table_widget').find('tbody').addClass('entities-list sortable ui-sortable');
        }
    };

  /**
   * Registers behaviours related to entity reference field widget.
   */
  Drupal.behaviors.entityBrowserEntityReferenceTable = {
    attach: function (context) {
      var sortableSelector = context.querySelectorAll('.table--widget-entity_reference_browser_table_widget .entities-list.sortable');
      sortableSelector.forEach(function (widget) {
        $(widget).find('.item-container .handle').parent().addClass('handle-cell');
        Sortable.create(widget, {
          handle: ".handle",
          draggable: '.item-container',
          onEnd: function onEnd() {
            return Drupal.entityBrowserEntityReferenceTable.entitiesReordered(widget);
          }
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


  Drupal.entityBrowserEntityReferenceTable = {};

  /**
   * Reacts on sorting of entities for the table widget.
   *
   * @param {object} widget
   *   Object with the sortable area.
   */
  Drupal.entityBrowserEntityReferenceTable.entitiesReordered = function (widget) {
    var items = $(widget).find('.item-container');
    var ids = [];
    for (var i = 0; i < items.length; i++) {
      ids[i] = $(items[i]).attr('data-entity-id');
    }
    $(widget).parent().parent().find('input[type*=hidden][name*="[target_id]"]').val(ids.join(' '));
  };
}(jQuery, Drupal));

