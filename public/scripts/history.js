(() => {
  const init = () => {
    const monthFilter = document.querySelector("[data-month-filter]");
    if (monthFilter) {
      monthFilter.querySelectorAll("select").forEach((select) => {
        select.addEventListener("change", () => monthFilter.submit());
      });
    }

    document.querySelectorAll("[data-delete-form]").forEach((form) => {
      form.addEventListener("submit", (event) => {
        const button = form.querySelector("[data-delete-confirm]");
        const message = button?.getAttribute("data-delete-confirm");
        if (message && !window.confirm(message)) {
          event.preventDefault();
        }
      });
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
