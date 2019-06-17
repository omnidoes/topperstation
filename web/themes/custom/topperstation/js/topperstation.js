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


  Drupal.behaviors.playerStage = {
    'attach': function (context) {


    }
  };

  // Drupal.behaviors.watchNext = {
  //   'attach': function (context) {

  //     var watchNextBlock = document.querySelectorAll('.block--views-blockvideos-block-recommended')[0];
  //     var blockTitle = watchNextBlock.querySelectorAll('.block__title')[0];
  //     var slickSlider = document.getElementById('watchnext');

  //     blockTitle.addEventListener('click', function(e) {
  //       e.preventDefault();
  //       console.log(slick);
  //     });

  //   }
  // };

})();
