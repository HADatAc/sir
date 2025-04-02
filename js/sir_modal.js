(function ($, Drupal) {
  Drupal.behaviors.openModalBehavior = {
    attach: function (context, settings) {
      // Remove any previous click handlers to avoid duplicates.
      $(document).off("click", ".view-media-button");

      // Bind the click event on elements with class "view-media-button".
      $(document).on("click", ".view-media-button", function (e) {
        e.preventDefault();
        console.log("view-media-button clicked");

        // Get the file URL from the data attribute.
        const modalUrl = $(this).data("view-url");
        if (!modalUrl) {
          console.error("data-view-url is undefined.");
          return;
        }

        // Get the native Drupal modal element.
        const drupalModal = document.getElementById("drupal-modal");
        if (drupalModal) {
          // Clear any previous content.
          drupalModal.innerHTML = "";
        }

        // Configure the PDF.js worker source.
        pdfjsLib.GlobalWorkerOptions.workerSrc =
          drupalSettings.sir_modal.baseUrl + "/modules/custom/std/js/pdf.worker.min.js";

        // Function to render the PDF using PDF.js.
        const renderPDF = function (response) {
          const pdfData = new Uint8Array(response);
          const loadingTask = pdfjsLib.getDocument({ data: pdfData });
          loadingTask.promise
            .then(function (pdf) {
              // Create a container for PDF pages.
              const container = document.createElement("div");
              container.className = "pdf-pages-container";

              // Loop through each page and render it onto a canvas.
              for (let i = 1; i <= pdf.numPages; i++) {
                pdf.getPage(i).then(function (page) {
                  const canvas = document.createElement("canvas");
                  const ctx = canvas.getContext("2d");
                  const viewport = page.getViewport({ scale: 1.5 });
                  canvas.height = viewport.height;
                  canvas.width = viewport.width;
                  canvas.style.margin = "0 auto";
                  page.render({ canvasContext: ctx, viewport: viewport });
                  container.appendChild(canvas);
                });
              }
              // Insert the PDF container into the element with id "pdf-container".
              const pdfContainer = document.getElementById("pdf-container");
              if (pdfContainer) {
                pdfContainer.innerHTML = "";
                pdfContainer.appendChild(container);
              }
            })
            .catch(function (error) {
              console.error("Error loading PDF:", error);
              const pdfContainer = document.getElementById("pdf-container");
              if (pdfContainer) {
                pdfContainer.innerHTML = "<p>Error Loading PDF.</p>";
              }
            });
        };

        // Build custom modal markup (the pdf-container goes inside the modal wrapper).
        const modalMarkup = `
          <div id="pdf-container"></div>
          <div class="my-modal-backdrop"></div>
        `;

        // Inject the custom markup into the native Drupal modal.
        if (drupalModal) {
          drupalModal.innerHTML = modalMarkup;
          // Make the modal visible.
          drupalModal.style.display = "block";
        }

        // Make an AJAX request to fetch the file as binary data.
        $.ajax({
          url: modalUrl,
          type: "GET",
          xhrFields: { responseType: "arraybuffer" },
          success: function (response, status, xhr) {
            const contentType = xhr.getResponseHeader("Content-Type");

            // Check the Content-Type and call the appropriate render function.
            if (contentType.includes("pdf")) {
              renderPDF(response);
            } else {
              const pdfContainer = document.getElementById("pdf-container");
              if (pdfContainer) {
                pdfContainer.innerHTML = `<p>Unsupported file type: ${contentType}</p>`;
              }
            }
          },
          error: function (xhr, status, error) {
            const pdfContainer = document.getElementById("pdf-container");
            if (pdfContainer) {
              pdfContainer.innerHTML = `<p>Error loading file. <a href="${modalUrl}" download>Click here to download</a>.</p>`;
            }
          }
        });
      });

      // Bind the close event on the close button and the backdrop.
      $(document)
        .off("click", "#modal-close, .my-modal-backdrop, .pdf-pages-container")
        .on("click", "#modal-close, .my-modal-backdrop, .pdf-pages-container", function (e) {
          e.preventDefault();
          const drupalModal = document.getElementById("drupal-modal");
          if (drupalModal) {
            drupalModal.style.display = "none";
            drupalModal.innerHTML = "";
          }
        });
    }
  };
})(jQuery, Drupal);
