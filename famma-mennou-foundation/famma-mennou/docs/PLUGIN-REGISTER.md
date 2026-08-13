# Plugin register (§26)

Minimal stack. Before adding anything new, answer §26's 7 questions and add a row.

| Plugin        | Purpose                          | Version | Reason                                            | Perf impact | Free |
|---------------|----------------------------------|---------|---------------------------------------------------|-------------|------|
| WooCommerce   | E-commerce core                  | current | Required foundation                               | Medium      | Yes  |
| FAMMA Core    | Business logic (this repo)       | 0.1.0   | Custom order flow / pricing / TN rules / tracking | Low         | Yes  |

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
