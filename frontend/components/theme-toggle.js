"use client";

import { useLayoutEffect } from "react";
import styles from "./foundation-controls.module.css";

const storageKey = "syncora-theme";

function resolveTheme() {
  const savedTheme = localStorage.getItem(storageKey);

  if (savedTheme === "light" || savedTheme === "dark") {
    return savedTheme;
  }

  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
}

function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  document.documentElement.style.colorScheme = theme;
}

export default function ThemeToggle() {
  useLayoutEffect(() => {
    applyTheme(resolveTheme());

    const preference = window.matchMedia("(prefers-color-scheme: dark)");
    const syncSystemTheme = () => {
      if (!localStorage.getItem(storageKey)) {
        applyTheme(preference.matches ? "dark" : "light");
      }
    };

    preference.addEventListener("change", syncSystemTheme);

    return () => preference.removeEventListener("change", syncSystemTheme);
  }, []);

  function toggleTheme() {
    const nextTheme =
      document.documentElement.dataset.theme === "dark" ? "light" : "dark";

    localStorage.setItem(storageKey, nextTheme);
    applyTheme(nextTheme);
  }

  return (
    <button
      className={styles.themeToggle}
      type="button"
      onClick={toggleTheme}
      aria-label="Toggle light and dark theme"
      title="Toggle color theme"
    >
      <svg className={styles.sunIcon} viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="3.5" />
        <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42" />
      </svg>
      <svg className={styles.moonIcon} viewBox="0 0 24 24" aria-hidden="true">
        <path d="M20.2 15.6A8.5 8.5 0 0 1 8.4 3.8 8.5 8.5 0 1 0 20.2 15.6Z" />
      </svg>
    </button>
  );
}
