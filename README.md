# Czas ↔ Pieniądz

Aplikacja do przeliczania wydatków na godziny pracy.

## Security Bingo

Legenda: 🟩 — zaimplementowane · ⬜ — brak / niepełne

| | **A** | **B** | **C** | **D** | **E** |
|---|-------|-------|-------|-------|-------|
| **1** | 🟩 Ochrona przed SQL injection (prepared statements / brak konkatenacji SQL) | 🟩 Metoda login/register przyjmuje dane tylko na POST, GET tylko renderuje widok | 🟩 Hasła nigdy nie są logowane w logach / errorach | ⬜ Limit prób logowania / blokada czasowa / CAPTCHA / Cloudflare o wielu nieudanych próbach | ⬜ Zwracam sensowne kody HTTP (np. 400/401/403 przy błędach) — tylko endpointy JSON |
| **2** | 🟩 Nie zdradzam, czy email istnieje – komunikat typu „Email lub hasło niepoprawne” | 🟩 CSRF token w formularzu logowania | 🟩 Po poprawnym logowaniu regeneruję ID sesji | 🟩 Waliduję złożoność hasła (min. długość itd.) | 🟩 Hasło nie jest przekazywane do widoków ani echo/var_dump |
| **3** | ⬜ Walidacja formatu email po stronie serwera | ⬜ CSRF token w formularzu rejestracji | 🟩 Cookie sesyjne ma flagę HttpOnly | 🟩 Przy rejestracji sprawdzam, czy email jest już w bazie | ⬜ Z bazy pobieram tylko minimalny zestaw danych o użytkowniku (`SELECT *`) |
| **4** | 🟩 UserRepository zarządzany jako singleton (`UsersRepository::getInstance()`) | ⬜ Ograniczam długość wejścia (email, hasło, imię…) — tylko hasło (8–72 zn.) | 🟩 Cookie sesyjne ma flagę Secure (przy HTTPS) | 🟩 Dane wyświetlane w widokach są escapowane (ochrona przed XSS) | 🟩 Mam poprawne wylogowanie – niszczę sesję użytkownika |
| **5** | ⬜ Logowanie i rejestracja dostępne tylko przez HTTPS | 🟩 Hasła przechowywane jako hash (bcrypt, `password_hash`) | 🟩 Cookie ma ustawione SameSite (`Lax`) | ⬜ W produkcji nie pokazuję stack trace / surowych błędów użytkownikowi | 🟩 Loguję nieudane próby logowania (bez haseł) do audytu |

**Wynik: 17 / 25**

### Uruchomienie

```bash
cp config.example.php config.php
docker compose up
```

Aplikacja: [http://localhost:8080](http://localhost:8080)

Logi audytu nieudanych logowań trafiają do logu PHP (`error_log`), np.:

```bash
docker compose logs php
```
