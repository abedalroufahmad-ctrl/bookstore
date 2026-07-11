# Book Store Web

React + Vite + TypeScript web app for the Book Store API.

## Setup

```bash
npm install
npm run dev
```

## Development

- **Web app:** http://localhost:5173
- **API proxy:** Requests to `/api` are proxied to `http://localhost:8000`
- **Backend:** Run `php artisan serve` in the `api/` directory

## Build

```bash
npm run build
```

## Environment

Create `.env` with:

```
VITE_API_URL=http://localhost:8000/api/v1
```

Or omit for dev (uses proxy). For production, set `VITE_API_URL` to your API URL.
