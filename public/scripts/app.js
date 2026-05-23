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
