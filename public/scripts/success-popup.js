(() => {
  let popupRoot = null;
  let hideTimer = null;
  let escapeHandler = null;

  const ensurePopup = () => {
    if (popupRoot) return popupRoot;

    popupRoot = document.createElement("div");
    popupRoot.className = "app-success-popup";
    popupRoot.setAttribute("role", "status");
    popupRoot.setAttribute("aria-live", "polite");
    popupRoot.hidden = true;
    popupRoot.innerHTML = `
      <div class="app-success-popup__card">
        <div class="app-success-popup__icon-wrap" aria-hidden="true">
          <span class="material-symbols-outlined app-success-popup__icon">check</span>
        </div>
        <div class="app-success-popup__message"></div>
      </div>
    `;

    document.body.appendChild(popupRoot);

    return popupRoot;
  };

  const clearHideTimer = () => {
    if (hideTimer !== null) {
      window.clearTimeout(hideTimer);
      hideTimer = null;
    }
  };

  const clearEscapeHandler = () => {
    if (escapeHandler !== null) {
      document.removeEventListener("keydown", escapeHandler);
      escapeHandler = null;
    }
  };

  window.hideSuccessPopup = () => {
    if (!popupRoot) return;

    clearHideTimer();
    clearEscapeHandler();
    popupRoot.classList.remove("app-success-popup--visible");
    popupRoot.hidden = true;
  };

  window.showSuccessPopup = (htmlMessage, { duration = 4000 } = {}) => {
    const root = ensurePopup();
    const messageEl = root.querySelector(".app-success-popup__message");
    if (!messageEl) return;

    clearHideTimer();
    clearEscapeHandler();

    messageEl.innerHTML = htmlMessage;
    root.hidden = false;

    requestAnimationFrame(() => {
      root.classList.add("app-success-popup--visible");
    });

    escapeHandler = (event) => {
      if (event.key === "Escape") {
        window.hideSuccessPopup();
      }
    };
    document.addEventListener("keydown", escapeHandler);

    if (duration > 0) {
      hideTimer = window.setTimeout(() => {
        window.hideSuccessPopup();
      }, duration);
    }
  };
})();
