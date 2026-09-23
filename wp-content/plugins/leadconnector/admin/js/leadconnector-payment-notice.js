(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var dismissButton = document.querySelector(".leadconnector-dismiss-button");
    if (!dismissButton) {
      return;
    }

    dismissButton.addEventListener("click", function () {
      var banner = document.querySelector(
        ".leadconnector-payment-notice-wrapper",
      );
      if (banner) {
        banner.style.display = "none";
      }
    });
  });
})();
