!function($, Drupal) {
  Drupal.behaviors.varbaseHeroSlider = {
    attach() {
      $(".carousel-control-pause").click((function() {
        $($(this).data("bs-target")).carousel("pause");
      }));
    }
  };
}(jQuery, Drupal);