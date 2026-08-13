# Plugin register (§26)

Minimal stack. Before adding anything new, answer §26's 7 questions and add a row.

| Plugin | Purpose | Version | Reason | Perf impact | Free |
|---|---|---|---|---|---|
| WooCommerce | E-commerce core | 11.0.1 | Required foundation | Medium | Yes |
| FAMMA Core | Business logic (this repo) | 0.2.0 | Custom order flow / pricing / TN rules / tracking | Low | Yes |
| TranslatePress | FR + Tunisian Derja, RTL | 3.3.2 | Only free option that covers WooCommerce content — Polylang's WooCommerce add-on is paid (ADR-005) | Medium | Yes |

### Must-use (this repo, not installed from wordpress.org)

| File | Purpose | Reason |
|---|---|---|
| `famma-env.php` | `.env` → `FAMMA_*` constants | The official image never regenerates `wp-config.php`, so `WORDPRESS_CONFIG_EXTRA` cannot carry configuration added later. See ARCHITECTURE. |
| `famma-local-mail.php` | Routes mail to Mailpit | The WordPress image has no mail transport. Guarded on `wp_get_environment_type() === 'local'` — inert anywhere else. |

### Theme

| Theme | Version | Reason |
|---|---|---|
| Kadence (parent) | 1.5.2 | ADR-004 — best free-tier WooCommerce template control, no page builder (§25) |
| FAMMA Child | 0.1.0 | This repo. Presentation only; all business logic stays in famma-core |

### TranslatePress configuration

- Default language **French**, single additional language **Arabic** — exactly what the free tier allows.
- **Automatic translation disabled** (`machine-translation: no`), not merely unconfigured: §5 forbids machine-translating commercial copy. Derja is written by a human.
- French at `/`, Arabic at `/ar/`. Verified: `/ar/` renders `dir="rtl" lang="ar"`.
- Language switcher floats **bottom-left** — bottom-right is taken by the WhatsApp button and the sticky mobile CTA owns the bottom edge. It should move into the header in Phase 5, where §9 expects it.
- Translations live in TranslatePress's own tables → they must be in scope for backups (§36) and migration (§43).

## Deliberately NOT installed (yet)
- **Page builder / Elementor** — Gutenberg + Site Editor only (§23, §25).
- **SEO plugin** — evaluate a single lightweight free option (e.g. a reputable
  free SEO plugin) vs. hand-rolled schema in famma-core. Decide before adding.
- **Caching / image optimisation** — server/host-level first; add one free
  plugin only if measured need (§23).
- **Multilingual plugin** — see ARCHITECTURE §5 decision.

## Rules
No nulled/pirated plugins or themes. Official/reputable sources only. Every
addition gets a row above with its configuration and an alternative considered.
