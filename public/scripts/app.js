const settingsForm = document.querySelector("[data-settings-form]");
if (settingsForm) {
  const submitBtn = settingsForm.querySelector("[data-settings-submit]");
  const incomeInput = settingsForm.querySelector('[name="income"]');
  const hoursInput = settingsForm.querySelector('[name="hours"]');
  const daysInput = settingsForm.querySelector('[name="days"]');
  const currencySelect = settingsForm.querySelector('[name="currency"]');
  const incomeCurrencyTag = settingsForm.querySelector("[data-income-currency-tag]");

  const syncIncomeCurrencyTag = () => {
    if (incomeCurrencyTag && currencySelect) {
      incomeCurrencyTag.textContent = currencySelect.value;
    }
  };

  const parseIncome = (raw) => {
    const normalized = String(raw).trim().replace(/\s/g, "").replace(",", ".");
    if (normalized === "") return 0;
    const value = parseFloat(normalized);
    return Number.isFinite(value) ? value : 0;
  };

  const snapshotForm = () => ({
    income: parseIncome(incomeInput?.value ?? ""),
    hours: parseFloat(hoursInput?.value ?? ""),
    days: parseFloat(daysInput?.value ?? ""),
    currency: currencySelect?.value ?? "",
  });

  const initial = snapshotForm();

  const isSetupMode =
    settingsForm.dataset.metricsIncomplete === "1" ||
    initial.income <= 0 ||
    initial.hours <= 0 ||
    initial.days <= 0;

  const isFieldEmpty = (value) => String(value).trim() === "";

  const fieldsValid = (state) => {
    if (isFieldEmpty(incomeInput?.value)) return false;
    if (isFieldEmpty(hoursInput?.value)) return false;
    if (isFieldEmpty(daysInput?.value)) return false;
    if (!state.currency) return false;

    return (
      state.income > 0 &&
      Number.isFinite(state.hours) &&
      state.hours > 0 &&
      state.hours <= 744 &&
      Number.isFinite(state.days) &&
      state.days >= 1 &&
      state.days <= 7
    );
  };

  const hasChanges = (state) =>
    Math.abs(state.income - initial.income) > 0.001 ||
    Math.abs(state.hours - initial.hours) > 0.001 ||
    Math.abs(state.days - initial.days) > 0.001 ||
    state.currency !== initial.currency;

  const updateSubmitState = () => {
    if (!submitBtn) return;

    const state = snapshotForm();
    const valid = fieldsValid(state);
    const canSave = valid && (isSetupMode || hasChanges(state));

    submitBtn.disabled = !canSave;
  };

  currencySelect?.addEventListener("change", syncIncomeCurrencyTag);

  settingsForm.addEventListener("input", updateSubmitState);
  settingsForm.addEventListener("change", updateSubmitState);

  settingsForm.addEventListener("submit", (event) => {
    const state = snapshotForm();
    if (!fieldsValid(state) || (!isSetupMode && !hasChanges(state))) {
      event.preventDefault();
    }
  });

  syncIncomeCurrencyTag();
  updateSubmitState();
}

document.querySelectorAll("[data-password-toggle]").forEach((button) => {
  button.addEventListener("click", () => {
    const wrap = button.closest(".auth-input-wrap");
    const input = wrap?.querySelector('input[type="password"], input[type="text"]');
    const icon = button.querySelector(".material-symbols-outlined");
    if (!input || !icon) return;

    const isHidden = input.type === "password";
    input.type = isHidden ? "text" : "password";
    icon.textContent = isHidden ? "visibility_off" : "visibility";
    button.setAttribute("aria-label", isHidden ? "Ukryj hasło" : "Pokaż hasło");
  });
});

const appHeader = document.querySelector("[data-app-header]");
if (appHeader) {
  const menuToggle = appHeader.querySelector("[data-app-menu-toggle]");
  const menuBackdrop = appHeader.querySelector("[data-app-menu-backdrop]");
  const profileWrap = appHeader.querySelector("[data-app-profile]");
  const profileToggle = appHeader.querySelector("[data-app-profile-toggle]");
  const profileDropdown = appHeader.querySelector("#app-header-dropdown");
  const drawerLinks = appHeader.querySelectorAll(".app-header__drawer-link");

  const setBodyScrollLock = (locked) => {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.body.classList.toggle("app-menu-scroll-lock", locked);
    document.body.style.paddingRight = locked && scrollbarWidth > 0 ? `${scrollbarWidth}px` : "";
  };

  const closeMobileMenu = () => {
    appHeader.classList.remove("app-header--menu-open");
    menuToggle?.setAttribute("aria-expanded", "false");
    menuToggle?.setAttribute("aria-label", "Otwórz menu");
    menuBackdrop?.setAttribute("hidden", "");
    menuBackdrop?.setAttribute("aria-hidden", "true");
    setBodyScrollLock(false);
  };

  const openMobileMenu = () => {
    closeProfileDropdown();
    appHeader.classList.add("app-header--menu-open");
    menuToggle?.setAttribute("aria-expanded", "true");
    menuToggle?.setAttribute("aria-label", "Zamknij menu");
    menuBackdrop?.removeAttribute("hidden");
    menuBackdrop?.setAttribute("aria-hidden", "false");
    setBodyScrollLock(true);
  };

  const closeProfileDropdown = () => {
    profileWrap?.classList.remove("app-header__profile--open");
    profileToggle?.setAttribute("aria-expanded", "false");
    profileDropdown?.setAttribute("hidden", "");
  };

  const openProfileDropdown = () => {
    closeMobileMenu();
    profileWrap?.classList.add("app-header__profile--open");
    profileToggle?.setAttribute("aria-expanded", "true");
    profileDropdown?.removeAttribute("hidden");
  };

  menuToggle?.addEventListener("click", () => {
    if (appHeader.classList.contains("app-header--menu-open")) {
      closeMobileMenu();
    } else {
      openMobileMenu();
    }
  });

  menuBackdrop?.addEventListener("click", closeMobileMenu);

  drawerLinks.forEach((link) => {
    link.addEventListener("click", closeMobileMenu);
  });

  profileToggle?.addEventListener("click", (event) => {
    event.stopPropagation();
    if (profileWrap?.classList.contains("app-header__profile--open")) {
      closeProfileDropdown();
    } else {
      openProfileDropdown();
    }
  });

  document.addEventListener("click", (event) => {
    if (!profileWrap?.contains(event.target)) {
      closeProfileDropdown();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    closeMobileMenu();
    closeProfileDropdown();
  });
}
