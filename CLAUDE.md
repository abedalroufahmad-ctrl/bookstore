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
- Domain features: public catalog, cart and checkout with stock reservation, warehouse quotes, PayPal and Stripe Checkout after quote (web + Flutter), POS sales, publisher commission accounts, multi-currency book prices (shopper currency on web + Flutter)
- Auth: customer `remember_me` → `JWT_REMEMBER_TTL`; employees use standard `JWT_TTL`

Do not treat generated `.claude/` reports or `.agents/skills/` content as product documentation.