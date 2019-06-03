(function () {

  'use strict';

  if ('NodeList' in window && !NodeList.prototype.forEach) {

    NodeList.prototype.forEach = function (callback, thisArg) {
      thisArg = thisArg || window;
      for (var i = 0; i < this.length; i++) {
        callback.call(thisArg, this[i], i, this);
      }
    };
  }

  Drupal.behaviors.triggers = {
    'attach': function (context) {

      var mobileTriggers = document.querySelectorAll('[trigger]');

      mobileTriggers.forEach(function(trigger) {

        trigger.addEventListener('click', function() {
          triggerHandler(this);
        });
      });

      function triggerHandler(trigger) {
        var targetTargetId = trigger.getAttribute('trigger-target');
        var targets = document.querySelectorAll('[target-name='+targetTargetId+']');

        trigger.classList.toggle('is-active');
        targets.forEach(function(target) {
          target.classList.toggle('is-active');
        });
      }
    }
  };

})();
