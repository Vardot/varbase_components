!function($, Drupal) {
  Drupal.behaviors.varbaseHeroSlider = {
    attach() {
      $(".carousel-control-pause").click((function() {
        $($(this).data("bs-target")).carousel("pause");
      }));
      const varbase_heroslider_carousel = document.getElementById("varbase_heroslider");
      varbase_heroslider_carousel.addEventListener("slide.bs.carousel", (event => {
        Drupal.drimage.init(document);
      })), varbase_heroslider_carousel.addEventListener("slid.bs.carousel", (event => {
        Drupal.drimage.init(document);
      }));
    }
  };
}(jQuery, Drupal);