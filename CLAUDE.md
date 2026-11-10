# Claude Code — project context

This repository is a bookstore monorepo:

- `api/`: Laravel 11 API with MongoDB and JWT auth
- `web/`: React 19 + Vite 7 storefront/admin app
- `app/`: Flutter mobile client

## Project docs

- [project.md](./project.md) — source of truth for product features and architecture
- [AUTHENTICATION.md](./AUTHENTICATION.md) — customer and employee JWT flow
- [README.md](./README.md), [web/README.md](./web/README.md), and [app/README.md](./app/README.md) — setup and local run notes

## Runtime snapshot

- API base: `http://localhost:8000/api/v1`
- Web app: `http://localhost:5173` via Vite proxy
- Auth: dual JWT guards (`customer`, `employee`)
- Domain features: public catalog, cart and checkout, warehouse quotes, PayPal/Stripe payments, POS sales, publisher commission accounts, and multi-currency pricing

Do not treat generated `.claude/` reports or `.agents/skills/` content as product documentation.
