/**
 * Boot script that reads user preference values from cookies
 * and sets data-* attributes on <html> before paint to prevent
 * flash of wrong theme/layout.
 */
import { PREFERENCE_REGISTRY } from '@/lib/preferences/preferences-config';

export function ThemeBootScript() {
    const registry = JSON.stringify(PREFERENCE_REGISTRY);

    const code = `
    (function () {
      try {
        var root = document.documentElement;
        var REGISTRY = ${registry};

        function readCookie(name) {
          var match = document.cookie.split("; ").find(function(c) {
            return c.startsWith(name + "=");
          });
          return match ? decodeURIComponent(match.split("=")[1]) : null;
        }

        function readPreference(key, definition) {
          var mode = definition.persistence;
          var value = null;

          if (!value && (mode === "client-cookie" || mode === "server-cookie")) {
            value = readCookie(key);
          }

          return definition.values.indexOf(value) >= 0 ? value : definition.defaultValue;
        }

        var preferences = {};

        Object.keys(REGISTRY).forEach(function(key) {
          var definition = REGISTRY[key];
          var value = readPreference(key, definition);

          preferences[key] = value;
          root.setAttribute(definition.attribute, value);
        });

        var mode = preferences.theme_mode;
        var resolvedMode =
          mode === "system" && window.matchMedia
            ? (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light")
            : mode === "dark"
              ? "dark"
              : "light";

        root.classList.toggle("dark", resolvedMode === "dark");
        root.style.colorScheme = resolvedMode;
      } catch (e) {
        console.warn("ThemeBootScript error:", e);
      }
    })();
  `;

    /* biome-ignore lint/security/noDangerouslySetInnerHtml: required for pre-hydration boot script */
    return <script dangerouslySetInnerHTML={{ __html: code }} />;
}
