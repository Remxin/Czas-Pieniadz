document.querySelectorAll('form[action="/register"]').forEach((form) => {
  const emailInput = form.querySelector('input[name="email"]');
  const usernameInput = form.querySelector('input[name="username"]');
  const passwordInput = form.querySelector('input[name="password"]');
  const confirmedPasswordInput = form.querySelector('input[name="password2"]');

  function isEmail(email) {
    return /\S+@\S+\.\S+/.test(email);
  }

  function arePasswordsSame(password, confirmedPassword) {
    return password === confirmedPassword;
  }

  function markValidation(element, condition) {
    if (!element) return;
    element.classList.toggle("no-valid", !condition);
  }

  function validateEmail() {
    if (!emailInput) return;
    markValidation(emailInput, isEmail(emailInput.value));
  }

  function validateUsername() {
    if (!usernameInput) return;
    markValidation(usernameInput, usernameInput.value.trim().length > 0);
  }

  function validatePassword() {
    if (!passwordInput || !confirmedPasswordInput) return;
    markValidation(
      confirmedPasswordInput,
      arePasswordsSame(passwordInput.value, confirmedPasswordInput.value)
    );
  }

  let emailTimer;
  let passwordTimer;
  let usernameTimer;

  if (emailInput) {
    emailInput.addEventListener("input", () => {
      clearTimeout(emailTimer);
      emailTimer = setTimeout(validateEmail, 400);
    });
  }

  if (usernameInput) {
    usernameInput.addEventListener("input", () => {
      clearTimeout(usernameTimer);
      usernameTimer = setTimeout(validateUsername, 400);
    });
  }

  if (confirmedPasswordInput && passwordInput) {
    const onPasswordInput = () => {
      clearTimeout(passwordTimer);
      passwordTimer = setTimeout(validatePassword, 400);
    };
    confirmedPasswordInput.addEventListener("input", onPasswordInput);
    passwordInput.addEventListener("input", onPasswordInput);
  }
});
