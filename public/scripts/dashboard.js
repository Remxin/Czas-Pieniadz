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

    [spendingForm, fixedForm].forEach((form) => {
      form?.addEventListener("submit", (event) => {
        syncHiddenFields();
        const name = String(nameInput?.value ?? "").trim();
        const price = parseFloat(String(priceInput?.value ?? "").replace(",", "."));
        if (name === "" || !Number.isFinite(price) || price <= 0) {
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
