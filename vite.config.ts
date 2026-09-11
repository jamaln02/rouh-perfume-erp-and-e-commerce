import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";
import { fileURLToPath, URL } from "node:url";

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const devApiProxyTarget = env.VITE_DEV_API_PROXY_TARGET || "http://127.0.0.1:8000";

  return {
  root: fileURLToPath(new URL(".", import.meta.url)),
  server: {
    host: true, // Listen on all addresses
    port: 5174,
    strictPort: false,
    // Allow only the project root. Previously this listed hard-coded Windows
    // paths (e.g. "E:/rouh-perfume") which broke the dev server on any other
    // machine. Restricting to "." is portable and still secure.
    fs: {
      allow: ["."],
    },
    hmr: {
      overlay: false,
    },
    // Local development: keep browser requests same-origin while Vite proxies
    // /api to Laravel. This makes the HttpOnly auth cookie belong to the
    // frontend origin and avoids CORS/cookie problems during local development.
    proxy: {
      "/api": {
        target: devApiProxyTarget,
        changeOrigin: true,
        secure: false,
      },
        "/products": {
    target: devApiProxyTarget,
    changeOrigin: true,
    secure: false,
  },
  "/storage": {
    target: devApiProxyTarget,
    changeOrigin: true,
    secure: false,
  },
    },
    headers: {
      // Dev server: keep no-store so hot-module-replacement is always fresh.
      // (Production caching is handled at the web-server / CDN layer, not here.)
      "Cache-Control": "no-store",
    },
    cors: {
      origin: true,
      methods: ["GET", "POST", "PATCH", "DELETE", "OPTIONS"],
    },
  },
  plugins: [react()],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
    extensions: [".js", ".ts", ".jsx", ".tsx"],
    dedupe: [
      "react",
      "react-dom",
      "react/jsx-runtime",
      "react/jsx-dev-runtime",
      "@tanstack/react-query",
      "@tanstack/query-core",
    ],
  },
  build: {
    // Target modern browsers — allows smaller, faster output (arrow-fns,
    // const/let, optional-chaining natively) instead of ES5 transpilation.
    target: "es2020",
    // Produce a manifest.json so the backend / a CDN can map entry names to
    // hashed file paths for cache-busting references.
    manifest: true,
    // Disable source maps in production to shrink bundle size and avoid
    // leaking source code. Enable only when debugging.
    sourcemap: false,
    // Vite 8 uses Oxc (Rolldown) for minification by default — fast and
    // effective, no separate esbuild dependency needed. (The old
    // minify:'esbuild' value is deprecated in Vite 8.)
    minify: true,
    // Raise the inline-asset limit slightly so small icons / fonts are
    // inlined as data URIs, reducing extra HTTP requests.
    assetsInlineLimit: 4096,
    // Report compressed (gzip) sizes in the build summary.
    reportCompressedSize: true,
    // Warn if a chunk exceeds 1000 kB (helps catch accidental bloat).
    chunkSizeWarningLimit: 1000,
    cssCodeSplit: true,
    rollupOptions: {
      output: {
        // Split third-party vendor code into separate long-cacheable chunks.
        // Vendor chunks change rarely, so browsers cache them across deploys
        // and only the app code needs to be re-downloaded.
        //
        // NOTE: Vite 8 ships with Rolldown, which requires manualChunks to be
        // a *function* (the object form is not supported). We inspect the
        // module id and route it to the appropriate vendor bucket.
        manualChunks(id: string): string | undefined {
          // Only split code inside node_modules (i.e. third-party deps).
          if (!id.includes("node_modules")) return undefined;

          // Core React runtime — re-fetched only on React upgrades.
          if (
            id.includes("node_modules/react/") ||
            id.includes("node_modules/react-dom/") ||
            id.includes("node_modules/react-router") ||
            id.includes("node_modules/react/jsx-runtime") ||
            id.includes("node_modules/react/jsx-dev-runtime") ||
            id.includes("node_modules/scheduler/")
          ) {
            return "vendor-react";
          }

          // Data-fetching / state layer — TanStack Query changes rarely.
          if (
            id.includes("node_modules/@tanstack/") ||
            id.includes("node_modules/@reduxjs/") ||
            id.includes("node_modules/react-redux/")
          ) {
            return "vendor-query";
          }

          // UI primitive library — large, changes rarely.
          if (
            id.includes("node_modules/@radix-ui/") ||
            id.includes("node_modules/class-variance-authority/") ||
            id.includes("node_modules/clsx/") ||
            id.includes("node_modules/tailwind-merge/")
          ) {
            return "vendor-radix";
          }

          // Charts — large, only loaded on dashboard / report pages.
          if (id.includes("node_modules/recharts/")) {
            return "vendor-charts";
          }

          // Utility / formatting / icon libraries.
          if (
            id.includes("node_modules/lucide-react/") ||
            id.includes("node_modules/sonner/")
          ) {
            return "vendor-utils";
          }

          // Everything else from node_modules goes into a generic vendor chunk.
          return "vendor";
        },
      },
    },
  },
  };
});
