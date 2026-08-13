# Order workflow (§2, §12)

```
NEW → PENDING CONFIRMATION → CONFIRMED → PROCESSING → SHIPPED → DELIVERED → COMPLETED
```
Exceptional: CANCELLED · FAILED DELIVERY · RETURNED · REFUSED · REFUNDED

## Mapping to WooCommerce statuses
| FAMMA stage          | WC status slug     | Source        |
|----------------------|--------------------|---------------|
| New                  | `pending`          | native        |
| Pending confirmation | `pending-confirm`  | famma-core    |
| Confirmed            | `confirmed`        | famma-core    |
| Processing           | `processing`       | native        |
| Shipped              | `shipped`          | famma-core    |
| Delivered            | `delivered`        | famma-core    |
| Completed            | `completed`        | native        |
| Cancelled            | `cancelled`        | native        |
| Failed delivery      | `failed-delivery`  | famma-core    |
| Returned             | `returned`         | famma-core    |
| Refused              | `refused`          | famma-core    |
| Refunded             | `refunded`         | native        |

## Payment rule
COD orders stay **unpaid** until the status becomes **Delivered**. This prevents
counting a placed-but-undelivered COD order as revenue (§12, §17).

### How it actually works (verified on WooCommerce 11.0.1)

`payment_complete()` alone is **not** enough: WooCommerce only performs the
payment bookkeeping when the order's current status is in
`woocommerce_valid_order_statuses_for_payment_complete` — by default
`on-hold, pending, failed, cancelled`. From a custom status such as
`delivered` it silently does nothing. Three filters in `Order_Statuses` are
therefore required:

| Filter | Priority | Why |
|---|---|---|
| `woocommerce_valid_order_statuses_for_payment_complete` | 10 | adds `delivered`, otherwise `payment_complete()` is a no-op |
| `woocommerce_order_is_paid_statuses` | 10 | adds `delivered`, otherwise `is_paid()` stays false and revenue reports ignore the order |
| `woocommerce_payment_complete_order_status` | **99** | keeps the order in `delivered`. `WC_Gateway_COD` registers the same filter and forces `completed`; gateways are instantiated after `plugins_loaded`, so at equal priority it would win and the order would leave `delivered` immediately |

Idempotence is guarded by the `_famma_cod_paid` meta, **not** by `date_paid`:
`WC_Abstract_Order::maybe_set_date_paid()` runs during `save()` — before this
transition — and already stamps the paid date as a side effect of the filter
above. Guarding on `date_paid` would return early and `payment_complete()`
would never run, so `woocommerce_payment_complete` would never fire and
**stock would never be decremented**.

### Where a COD order starts, and why it matters

`WC_Gateway_COD` drops a fresh order straight into `processing`, and
`processing` is one of WooCommerce's paid statuses. A brand-new, undelivered
order therefore reported `is_paid() === true` — the exact false positive §12
forbids. Observed on a real browser checkout, not in theory.

Two changes fix it:

- `woocommerce_cod_process_payment_order_status` → **`pending-confirm`**, which
  is the first step of the §2 workflow anyway;
- `processing` is removed from both the paid statuses and the reporting
  statuses, because §2 places PROCESSING *before* SHIPPED — the parcel is being
  prepared and no cash has been collected.

> Valid while cash on delivery is the only gateway (§2). **Adding an online
> payment method later means revisiting `paid_statuses()`**, since a prepaid
> order legitimately is paid at `processing`.

### Verified behaviour
A real browser order at 375×812, placed with no email and a local 8-digit
phone number:

| Stage | `is_paid()` |
|---|---|
| `pending-confirm` (on placement) | false |
| `confirmed` | false |
| `processing` | false |
| `shipped` | false |
| **`delivered`** | **true**, `date_paid` stamped |

Status stays `delivered`, stock decremented exactly once, a single
`COD payment collected on delivery` note. Replaying the hook changes nothing.
An invalid phone number is rejected with the bilingual message before the
order is created.

> **Ouvert (D6)** — le stock n'est décrémenté qu'à la livraison. Réserver le
> stock plus tôt (à `confirmed`) est une décision métier qui n'a pas encore
> été prise ; en l'état, deux commandes peuvent porter sur le même article.
