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
