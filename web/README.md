# Bookstore Web

React 19 + Vite 7 + TypeScript + Tailwind CSS 4 storefront, admin and POS SPA (Arabic/English, navy theme).

**Full documentation:** [project.md](../project.md)

```bash
npm install
npm run dev    # http://localhost:5173 — /api → 127.0.0.1:8000
npm run build  # type-check + production build
npx eslint src
```

Requires Node 20.19+ or 22.12+. Set `VITE_API_URL` in `.env` to call a remote API instead of the dev proxy.
