import { useEffect } from "react";
import "./fstyle.css";

const WebLightbox = ({ url, onClose, title, type = "iframe" }) => {
  useEffect(() => {
    const iframeUrl =
      type === "video"
        ? `${url}?autoplay=1&TB_iframe=true&width=800&height=450`
        : `${url}?TB_iframe=true&width=600&height=550`;

    if (typeof window.tb_show === "function") {
      window.tb_show(title, iframeUrl);
    }

    const tbLoad = document.getElementById("TB_load");
    if (tbLoad) {
      tbLoad.style.display = "none"; // Change display to none
  }




    const handleClose = () => {
      const closeButton = document.getElementById("TB_closeWindowButton");
      const closeAjaxWindow = document.getElementById("TB_closeAjaxWindow");
      const tbWindow = document.getElementById("TB_window");

      if (closeButton) {
        closeButton.addEventListener("click", onClose);
      }

      if (closeAjaxWindow) {
        // Create the responsive view buttons
        const responsiveView = document.createElement("span");
        responsiveView.className = "responsive-view";
        responsiveView.innerHTML = `
          <span class="actions">
            <a class="desktop" href="#"><span data-view="desktop" class="active dashicons dashicons-desktop"></span></a>
            <a class="tablet" href="#"><span data-view="tablet" class="dashicons dashicons-tablet"></span></a>
            <a class="mobile" href="#"><span data-view="mobile" class="dashicons dashicons-smartphone"></span></a>
          </span>
        `;

        // Append the responsive view buttons to the closeAjaxWindow div
        closeAjaxWindow.prepend(responsiveView);

        // Add event listeners to the responsive view buttons
        const buttons = responsiveView.querySelectorAll("a");
        buttons.forEach(button => {
          button.addEventListener("click", (event) => {
            event.preventDefault();
            buttons.forEach(btn => btn.querySelector("span").classList.remove("active"));
            button.querySelector("span").classList.add("active");

            const view = button.querySelector("span").getAttribute("data-view");

            // Add the class to the TB_window div
            if (tbWindow) {
              tbWindow.className = `astra-portfolio-type-iframe astra-portfolio ${view}`;
            }

            // Add the class to the iframe element
            const iframe = tbWindow.querySelector("iframe");
            if (iframe) {
              iframe.className = view;
            }
          });
        });
      }
    };

    setTimeout(handleClose, 500);

    // Add the lightbox-active class to the body
    document.body.classList.add("lightbox-active");

    return () => {
      const closeButton = document.getElementById("TB_closeWindowButton");
      if (closeButton) {
        closeButton.removeEventListener("click", onClose);
      }

      // Remove the lightbox-active class from the body
      document.body.classList.remove("lightbox-active");
    };
  }, [url, type, title, onClose]);

  return null;
};

export default WebLightbox;