# Bookstore Web

React 19 + Vite 7 + TypeScript + Tailwind CSS 4 storefront/admin app for the bookstore monorepo. It covers the public catalog, customer flows, employee admin screens, POS views, and publisher reports with Arabic/English support and a navy theme.

**Project docs:** [project.md](../project.md)

```bash
npm install
npm run dev    # http://localhost:5173 — Vite proxy forwards /api to the Laravel API
npm run build  # type-check + production build
npm run lint   # eslint .
```

Requires Node 20.19+ or 22.12+. Set `VITE_API_URL` in `.env` to point at a remote API instead of the local dev proxy.
