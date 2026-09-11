import { createRoot } from "react-dom/client";
import { HelmetProvider } from "react-helmet-async";
import { Provider } from "react-redux";
import App from "./App";
import { store } from "./store";
import "./index.css";

// Centralize browser API credentials so every same-origin/cross-origin API
// request carries the HttpOnly ROUH session cookie. Non-API requests remain
// untouched. This also keeps legacy fetch callers safe without a risky mass rewrite.
if (!(window as typeof window & { __rouhFetchWrapped?: boolean }).__rouhFetchWrapped) {
  const nativeFetch = window.fetch.bind(window);
  const configuredApiBase = String(import.meta.env.VITE_API_URL || "").replace(/\/$/, "");
  window.fetch = (input: RequestInfo | URL, init?: RequestInit) => {
    const url = input instanceof Request ? input.url : String(input);
    const isApiRequest = /\/api(?:\/|$)/.test(url)
      || (configuredApiBase !== "" && url.startsWith(`${configuredApiBase}/api/`));
    if (isApiRequest) {
      return nativeFetch(input, { ...(init ?? {}), credentials: init?.credentials ?? "include" });
    }
    return nativeFetch(input, init);
  };
  (window as typeof window & { __rouhFetchWrapped?: boolean }).__rouhFetchWrapped = true;
}

createRoot(document.getElementById("root")!).render(
  <HelmetProvider>
    <Provider store={store}>
      <App />
    </Provider>
  </HelmetProvider>
);
