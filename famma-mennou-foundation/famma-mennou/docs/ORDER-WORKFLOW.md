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

### Verified behaviour
An order taken through `pending-confirm → confirmed → shipped → delivered`:
status stays `delivered`, `is_paid()` true, `date_paid` stamped, stock
decremented exactly once, one `COD payment collected on delivery` note.
Replaying the hook changes nothing.

> **Ouvert (D6)** — le stock n'est décrémenté qu'à la livraison. Réserver le
> stock plus tôt (à `confirmed`) est une décision métier qui n'a pas encore
> été prise ; en l'état, deux commandes peuvent porter sur le même article.
