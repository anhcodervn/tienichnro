# Architecture

## Request flow

```text
Blade checkout
  -> Features/Client/Topup/Requests/StoreOrderRequest
  -> Features/Topup/Services/OrderService
  -> Features/Topup/Services/OrderPricingService
  -> orders + wallet ledger
  -> Features/Topup/Jobs/ProcessTopupOrder
  -> Features/Topup/Services/TopupService
  -> Features/Topup/Contracts/TopupProviderInterface
```

## Feature ownership

- `App\Features\Client\Topup` owns public/account HTTP controllers, form requests and routes.
- `App\Features\Topup` owns shared order, payment, fulfillment, provider and realtime behavior.
- `App\Features\Admin\Topup` owns catalog, provider and order administration.
- `App\Features\Recharge` authenticates bank callbacks before delegating order matching to the Topup domain.
- Eloquent models remain in `App\Models` and reusable order mailables remain in `App\Mail\Orders`.

## Boundaries

- Client routes render Blade and only use JavaScript for form preview, copy buttons and payment polling.
- Admin Vue is mounted only below `/admin` and calls protected `/api/admin-api/*` endpoints.
- Wallet mutations, payment matching and status transitions are transactional and idempotent.
- Provider work happens after the database transaction commits.
- Guest order access requires code plus normalized email, a session grant, ownership, or a signed URL.

## Domain tables

`games`, `game_servers`, `topup_packages`, `orders`, `admin_audit_logs`, plus the reused `wallets`, `wallet_transactions`, `payment_transactions`, settings and SEO tables.
