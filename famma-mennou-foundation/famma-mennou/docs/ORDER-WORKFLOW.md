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
COD orders stay **unpaid** until the status becomes **Delivered**. On that
transition, `Order_Statuses::mark_cod_paid_on_delivery()` calls
`payment_complete()` (records paid date, decrements stock once) and the tracking
layer fires the real **Purchase** event with a dedup `event_id`. This prevents
counting a placed-but-undelivered COD order as revenue (§12, §17).
