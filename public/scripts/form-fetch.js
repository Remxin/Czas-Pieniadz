(() => {
  const parseJsonResponse = async (response) => {
    const contentType = response.headers.get("content-type") ?? "";
    if (contentType.includes("application/json")) {
      return response.json();
    }

    const text = await response.text();
    const isHtml = text.trimStart().startsWith("<!");
    return {
      ok: false,
      message: isHtml
        ? "Nie udało się przetworzyć odpowiedzi serwera."
        : text.trim() || "Nie udało się przetworzyć odpowiedzi serwera.",
    };
  };

  const formDataToObject = (form) => {
    const data = {};
    new FormData(form).forEach((value, key) => {
      data[key] = value;
    });
    return data;
  };

  const resolveFormUrl = (form) => {
    const action =
      form.dataset.fetchUrl ??
      form.getAttribute("action") ??
      window.location.pathname;
    return new URL(action, window.location.origin).href;
  };

  const refreshSession = async () => {
    const response = await fetch("/auth/refresh", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: "{}",
    });

    if (response.status !== 200) {
      return false;
    }

    const data = await parseJsonResponse(response);
    return data.ok === true;
  };

  const postJson = async (url, payload) =>
    fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

  window.submitFormFetch = async (form, options = {}) => {
    const {
      onSuccess,
      onError,
      successMessage,
      showSuccessPopup = true,
    } = options;

    const submitBtn = form.querySelector('[type="submit"]');
    const originalDisabled = submitBtn?.disabled ?? false;

    if (submitBtn) {
      submitBtn.disabled = true;
    }

    try {
      const url = resolveFormUrl(form);
      const payload = formDataToObject(form);

      let response = await postJson(url, payload);

      if (response.status === 401) {
        const refreshed = await refreshSession();
        if (refreshed) {
          response = await postJson(url, payload);
        }
      }

      if (response.status === 401) {
        window.location.href = "/login";
        return null;
      }

      const data = await parseJsonResponse(response);

      if (data.ok === true) {
        const shouldShowPopup =
          showSuccessPopup &&
          typeof window.showSuccessPopup === "function" &&
          (successMessage || data.message) &&
          data.changed !== false;

        if (shouldShowPopup) {
          window.showSuccessPopup(successMessage || data.message);
        }

        if (typeof onSuccess === "function") {
          onSuccess(data, form);
        }

        return data;
      }

      if (typeof onError === "function") {
        onError(data, form);
      } else if (data.message) {
        window.alert(data.message);
      }

      return data;
    } catch {
      const fallback = { ok: false, message: "Wystąpił błąd połączenia. Spróbuj ponownie." };
      if (typeof onError === "function") {
        onError(fallback, form);
      } else {
        window.alert(fallback.message);
      }
      return fallback;
    } finally {
      if (submitBtn && typeof onSuccess !== "function") {
        submitBtn.disabled = originalDisabled;
      }
    }
  };
})();
