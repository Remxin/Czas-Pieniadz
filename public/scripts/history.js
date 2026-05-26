(() => {
  const init = () => {
    const monthFilter = document.querySelector("[data-month-filter]");
    if (monthFilter) {
      monthFilter.querySelectorAll("select").forEach((select) => {
        select.addEventListener("change", () => monthFilter.submit());
      });
    }

    const updateHistorySummary = (summary) => {
      if (!summary) return;

      const hoursValue = document.querySelector("[data-history-hours]");
      if (hoursValue) {
        hoursValue.innerHTML = `${summary.monthlyHoursFormatted} <span class="history-stat-card__unit">godzin</span>`;
      }

      const hoursBar = document.querySelector("[data-history-hours-bar]");
      if (hoursBar) {
        hoursBar.style.width = `${summary.workMonthPercent}%`;
      }

      const hoursNote = document.querySelector("[data-history-hours-note]");
      if (hoursNote) {
        hoursNote.textContent = `${summary.workMonthPercent}% standardowego miesiąca pracy`;
      }

      const hourlyRate = document.querySelector("[data-history-hourly-rate]");
      if (hourlyRate) {
        hourlyRate.textContent = summary.hourlyRateFormatted;
      }

      const hourlyNoteWrap = document.querySelector("[data-history-hourly-note-wrap]");
      const hourlyNote = document.querySelector("[data-history-hourly-note]");
      if (hourlyNoteWrap && hourlyNote) {
        if (summary.hourlyRateNote) {
          hourlyNote.textContent = summary.hourlyRateNote;
          hourlyNoteWrap.removeAttribute("hidden");
        } else {
          hourlyNoteWrap.setAttribute("hidden", "");
        }
      }

      const totalEl = document.querySelector("[data-history-total]");
      if (totalEl) totalEl.textContent = summary.monthlyTotalFormatted;

      const fixedWrap = document.querySelector("[data-history-fixed-wrap]");
      const fixedEl = document.querySelector("[data-history-fixed]");
      if (fixedWrap && fixedEl) {
        if (summary.hasFixedCosts) {
          fixedEl.textContent = summary.monthlyFixedFormatted;
          fixedWrap.removeAttribute("hidden");
        } else {
          fixedWrap.setAttribute("hidden", "");
        }
      }

      const entryCount = document.querySelector("[data-history-entry-count]");
      if (entryCount) entryCount.textContent = String(summary.entryCount);

      const freedomLabel = document.querySelector("[data-history-freedom-label]");
      if (freedomLabel) freedomLabel.textContent = summary.freedomLabel;

      const freedomBar = document.querySelector("[data-history-freedom-bar]");
      if (freedomBar) freedomBar.style.width = `${summary.freedomPercent}%`;

      const limitAlert = document.querySelector("[data-history-limit-alert]");
      const limitText = document.querySelector("[data-history-limit-text]");
      if (limitAlert && limitText) {
        if (summary.showLimitAlert) {
          limitText.textContent = summary.hoursOverLimitFormatted;
          limitAlert.removeAttribute("hidden");
        } else {
          limitAlert.setAttribute("hidden", "");
        }
      }

      const tableFooter = document.querySelector("[data-history-table-footer]");
      const tableFooterText = document.querySelector("[data-history-table-footer] p");
      if (tableFooter && tableFooterText) {
        if (summary.entryCountLabel) {
          tableFooterText.textContent = summary.entryCountLabel;
          tableFooter.removeAttribute("hidden");
        } else {
          tableFooter.setAttribute("hidden", "");
        }
      }
    };

    const showEmptyState = () => {
      const tableWrap = document.querySelector("[data-history-table-wrap]");
      if (!tableWrap) return;

      const monthLabel = tableWrap.dataset.monthLabel || "";
      tableWrap.outerHTML = `<p class="history-empty">Brak kosztów w ${monthLabel}. Dodaj wydatek na pulpicie.</p>`;
    };

    document.querySelectorAll("[data-delete-form]").forEach((form) => {
      form.addEventListener("submit", async (event) => {
        event.preventDefault();

        const button = form.querySelector("[data-delete-confirm]");
        const message = button?.getAttribute("data-delete-confirm");
        if (message && !window.confirm(message)) {
          return;
        }

        await window.submitFormFetch(form, {
          successMessage: "<p>Wpis został usunięty.</p>",
          onSuccess: (data) => {
            const row = form.closest("tr");
            row?.remove();

            updateHistorySummary(data.summary);

            const tbody = document.querySelector("[data-history-table-wrap] tbody");
            if (data.summary?.hasRows === false) {
              showEmptyState();
            } else if (tbody && tbody.children.length === 0) {
              showEmptyState();
            }
          },
          onError: (data) => {
            if (data.message) {
              window.alert(data.message);
            }
          },
        });
      });
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
