# LoyaltyOS

Multi-tenant loyalty platform with a Laravel API and React dashboard.

## Local setup

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve
php artisan queue:work
```

In another terminal:

```bash
cd frontend
npm install
npm run dev
```

Demo login: `owner@example.com` / `password`.

Set `BROADCAST_CONNECTION=pusher` and the Pusher credentials in `backend/.env`
to enable live broadcasts. The default `log` driver keeps local development
credential-free.

## Signed order API

Send `POST /api/integrations/orders` with:

- `X-Loyalty-Key`
- `X-Loyalty-Timestamp`
- `X-Loyalty-Signature`: HMAC-SHA256 of `{timestamp}.{raw-json-body}`

The unique tuple `(business, source, external order ID)` and ledger
idempotency keys prevent duplicate point awards.
