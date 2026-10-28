(() => {
  const storageKey = 'studimate-theme';
  const root = document.documentElement;

  function getSavedTheme() {
    try {
      return localStorage.getItem(storageKey);
    } catch (error) {
      return null;
    }
  }

  function applyTheme(theme) {
    const isDark = theme === 'dark';
    if (isDark) {
      root.dataset.theme = 'dark';
    } else {
      delete root.dataset.theme;
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      const label = isDark ? 'Light mode' : 'Dark mode';
      button.setAttribute('aria-label', `Switch to ${label.toLowerCase()}`);
      button.setAttribute('title', `Switch to ${label.toLowerCase()}`);
      button.setAttribute('aria-pressed', String(isDark));

      const icon = button.querySelector('[data-theme-icon]');
      if (icon) {
        icon.textContent = isDark ? '☀' : '☾';
      }

      const text = button.querySelector('[data-theme-label]');
      if (text) {
        text.textContent = label;
      }
    });
  }

  applyTheme(getSavedTheme());

  function initializeThemeToggles() {
    applyTheme(root.dataset.theme);
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
        applyTheme(theme);
        try {
          localStorage.setItem(storageKey, theme);
        } catch (error) {
          // The current page still changes theme when storage is unavailable.
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeThemeToggles, { once: true });
  } else {
    initializeThemeToggles();
  }
})();
