(() => {
  const init = () => {
    const dashboardForm = document.querySelector("[data-dashboard-form]");
    if (!dashboardForm) return;

    const metrics =
      window.DASHBOARD_METRICS &&
      typeof window.DASHBOARD_METRICS === "object"
        ? {
            earnings: Number(window.DASHBOARD_METRICS.earnings) || 0,
            work_hours_per_month:
              Number(window.DASHBOARD_METRICS.work_hours_per_month) || 0,
          }
        : null;

    const nameInput = dashboardForm.querySelector("[data-expense-name]");
    const priceInput = dashboardForm.querySelector("[data-expense-price]");
    const hintEl = dashboardForm.querySelector("[data-dashboard-hint]");
    const actionsSection = dashboardForm.querySelector("[data-dashboard-actions]");
    const iconSelect = dashboardForm.querySelector("[data-expense-icon]");
    const spendingForm = dashboardForm.querySelector("[data-spending-form]");
    const fixedForm = dashboardForm.querySelector("[data-fixed-form]");

    const statHours = document.querySelector("[data-stat-hours]");
    const statDays = document.querySelector("[data-stat-days]");
    const statPercent = document.querySelector("[data-stat-percent]");
    const statHoursBar = document.querySelector("[data-stat-hours-bar]");
    const statDaysBar = document.querySelector("[data-stat-days-bar]");
    const statPercentBar = document.querySelector("[data-stat-percent-bar]");

    const WORK_DAY_HOURS = 8;

    const showHint = (message) => {
      if (!hintEl) return;
      if (!message) {
        hintEl.textContent = "";
        hintEl.setAttribute("hidden", "");
        return;
      }
      hintEl.textContent = message;
      hintEl.removeAttribute("hidden");
    };

    const formatNumber = (value, decimals = 1) =>
      value.toLocaleString("pl-PL", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      });

    const metricsReady = () =>
      metrics !== null &&
      metrics.earnings > 0 &&
      metrics.work_hours_per_month > 0;

    const calculate = (price) => {
      if (!metricsReady() || price <= 0) return null;

      const lifeHours = (price * metrics.work_hours_per_month) / metrics.earnings;
      const workDays = lifeHours / WORK_DAY_HOURS;
      const incomePercent = (price / metrics.earnings) * 100;

      return { lifeHours, workDays, incomePercent };
    };

    const barPercent = (value, max) => {
      if (max <= 0) return 0;
      return Math.min(100, Math.round((value / max) * 100));
    };

    const syncHiddenFields = () => {
      const name = String(nameInput?.value ?? "").trim();
      const price = String(priceInput?.value ?? "").trim();
      const icon = iconSelect?.value ?? "shopping_bag";

      [spendingForm, fixedForm].forEach((form) => {
        if (!form) return;
        const hiddenName = form.querySelector("[data-hidden-name]");
        const hiddenPrice = form.querySelector("[data-hidden-price]");
        const hiddenIcon = form.querySelector("[data-hidden-icon]");
        if (hiddenName) hiddenName.value = name;
        if (hiddenPrice) hiddenPrice.value = price;
        if (hiddenIcon) hiddenIcon.value = icon;
      });
    };

    const resetStats = () => {
      if (statHours) statHours.textContent = "—";
      if (statDays) statDays.textContent = "—";
      if (statPercent) statPercent.textContent = "—";
      if (statHoursBar) statHoursBar.style.width = "0%";
      if (statDaysBar) statDaysBar.style.width = "0%";
      if (statPercentBar) statPercentBar.style.width = "0%";
    };

    const resetCalculator = () => {
      if (nameInput) nameInput.value = "";
      if (priceInput) priceInput.value = "";
      showHint("");
      actionsSection?.setAttribute("hidden", "");
      resetStats();
    };

    const updateStats = (result) => {
      if (!result || !metrics) return;

      const workHours = metrics.work_hours_per_month;
      const maxDays = workHours / WORK_DAY_HOURS;

      if (statHours) statHours.textContent = formatNumber(result.lifeHours);
      if (statDays) statDays.textContent = formatNumber(result.workDays);
      if (statPercent) {
        statPercent.textContent = `${Math.round(result.incomePercent)}%`;
      }
      if (statHoursBar) {
        statHoursBar.style.width = `${barPercent(result.lifeHours, workHours)}%`;
      }
      if (statDaysBar) {
        statDaysBar.style.width = `${barPercent(result.workDays, maxDays)}%`;
      }
      if (statPercentBar) {
        statPercentBar.style.width = `${barPercent(result.incomePercent, 100)}%`;
      }
    };

    const runCalculation = () => {
      const name = String(nameInput?.value ?? "").trim();
      const price = parseFloat(String(priceInput?.value ?? "").replace(",", "."));

      if (name === "") {
        showHint("Podaj nazwę wydatku.");
        nameInput?.focus();
        return;
      }
      if (!Number.isFinite(price) || price <= 0) {
        showHint("Podaj prawidłową cenę większą od zera.");
        priceInput?.focus();
        return;
      }
      if (!metricsReady()) {
        showHint("Uzupełnij wynagrodzenie i godziny miesięczne w ustawieniach.");
        return;
      }

      const result = calculate(price);
      if (!result) {
        showHint("Nie udało się przeliczyć. Sprawdź dane w ustawieniach.");
        return;
      }

      showHint("");
      updateStats(result);
      actionsSection?.removeAttribute("hidden");
      syncHiddenFields();
    };

    const escapeHtml = (value) =>
      String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");

    const buildExpenseItem = (spending) => {
      const li = document.createElement("li");
      li.className = "app-expense-item";
      li.innerHTML = `
        <div class="app-expense-item__left">
          <span class="app-expense-item__icon ${escapeHtml(spending.theme_class)} material-symbols-outlined" aria-hidden="true">${escapeHtml(spending.icon)}</span>
          <div>
            <p class="app-expense-item__name">${escapeHtml(spending.name)}</p>
            <p class="app-expense-item__meta">${escapeHtml(spending.meta_label)}</p>
          </div>
        </div>
        <div class="app-expense-item__right">
          <p class="app-expense-item__amount">${escapeHtml(spending.amount_formatted)}</p>
          <p class="app-expense-item__hours">${escapeHtml(spending.life_hours_label)} pracy</p>
        </div>
      `;
      return li;
    };

    const prependSpending = (spending) => {
      const recentSection = document.querySelector("[data-recent-spendings]");
      if (!recentSection || !spending) return;

      const emptyHint = recentSection.querySelector("[data-recent-empty]");
      emptyHint?.remove();

      let list = recentSection.querySelector(".app-expense-list");
      if (!list) {
        list = document.createElement("ul");
        list.className = "app-expense-list";
        recentSection.appendChild(list);
      }

      list.prepend(buildExpenseItem(spending));

      while (list.children.length > 5) {
        list.lastElementChild?.remove();
      }
    };

    const updateSummary = (summary) => {
      if (!summary) return;

      document.querySelectorAll("[data-summary-total]").forEach((el) => {
        el.textContent = summary.monthlyTotalFormatted;
      });

      document.querySelectorAll("[data-summary-hours]").forEach((el) => {
        el.textContent = String(summary.monthlyHoursShort);
      });

      document.querySelectorAll("[data-summary-hours-formatted]").forEach((el) => {
        el.textContent = summary.monthlyHoursFormatted;
      });

      document.querySelectorAll("[data-summary-fixed]").forEach((el) => {
        if (summary.hasFixedCosts) {
          el.textContent = summary.monthlyFixedFormatted;
          el.closest("[data-summary-fixed-wrap]")?.removeAttribute("hidden");
        } else {
          el.closest("[data-summary-fixed-wrap]")?.setAttribute("hidden", "");
        }
      });

      document.querySelectorAll("[data-summary-freedom-label]").forEach((el) => {
        el.textContent = summary.freedomLabel;
      });

      document.querySelectorAll("[data-summary-freedom-bar]").forEach((el) => {
        el.style.width = `${summary.freedomPercent}%`;
      });

      document.querySelectorAll("[data-summary-limit-alert]").forEach((el) => {
        if (summary.showLimitAlert) {
          const strong = el.querySelector("strong");
          if (strong) {
            strong.textContent = `${summary.hoursOverLimitFormatted}h`;
          }
          el.removeAttribute("hidden");
        } else {
          el.setAttribute("hidden", "");
        }
      });
    };

    const validateBeforeSubmit = () => {
      syncHiddenFields();
      const name = String(nameInput?.value ?? "").trim();
      const price = parseFloat(String(priceInput?.value ?? "").replace(",", "."));
      if (name === "" || !Number.isFinite(price) || price <= 0) {
        return false;
      }
      return true;
    };

    const bindFetchForm = (form, successMessage) => {
      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (!validateBeforeSubmit()) return;

        await window.submitFormFetch(form, {
          successMessage,
          onSuccess: (data) => {
            resetCalculator();
            if (data.spending) {
              prependSpending(data.spending);
            }
            updateSummary(data.summary);
          },
          onError: (data) => {
            if (data.message) {
              showHint(data.message);
            }
          },
        });
      });
    };

    dashboardForm.addEventListener("click", (event) => {
      if (event.target.closest("[data-calc-btn]")) {
        event.preventDefault();
        runCalculation();
      }
    });

    dashboardForm.addEventListener("keydown", (event) => {
      if (event.key !== "Enter") return;
      if (!event.target.matches("[data-expense-name], [data-expense-price]")) return;
      event.preventDefault();
      runCalculation();
    });

    iconSelect?.addEventListener("change", syncHiddenFields);

    bindFetchForm(
      spendingForm,
      "<p>Wydatek został zapisany.</p>"
    );
    bindFetchForm(
      fixedForm,
      "<p>Koszt stały został zapisany.</p>"
    );
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
