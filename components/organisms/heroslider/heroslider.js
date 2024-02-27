!function(Drupal, once) {
  Drupal.behaviors.varbaseHeroSliderPause = {
    attach: function(context) {
      once("pause-heroslider", ".carousel-control-pause", context).forEach((element => {
        element.addEventListener("click", (e => {
          bootstrap.Carousel.getInstance(document.querySelector(element.dataset.bsTarget)).pause();
        }));
      }));
    }
  }, Drupal.behaviors.varbaseHeroSliderDrimage = {
    attach: function(context) {
      document.querySelector(".carousel.varbase-heroslider", context).addEventListener("slid.bs.carousel", (event => {
        Drupal.drimage.init(document.querySelector(".carousel.varbase-heroslider", context));
      }));
    }
  };
}(Drupal, once);