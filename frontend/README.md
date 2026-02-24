# Book Store Frontend

React + Vite + TypeScript frontend for the Book Store API.

## Setup

```bash
npm install
npm run dev
```

## Development

- **Frontend:** http://localhost:5173
- **API proxy:** Requests to `/api` are proxied to `http://localhost:8000`
- **Backend:** Run `php artisan serve` in the project root

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
