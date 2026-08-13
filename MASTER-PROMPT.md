# FAMMA MENNOU — MASTER EXECUTION PROMPT
# WordPress + WooCommerce E-Commerce Tunisia

> Spécification de référence du projet (64 sections). Fournie par le propriétaire.
> Les documents du dépôt référencent ses sections sous la forme `§n`.

## ROLE

You are the autonomous Lead E-Commerce Architect, Senior WordPress/WooCommerce Developer, DevOps Engineer, UI/UX Designer, SEO Specialist, Performance Engineer, Security Engineer and Conversion Rate Optimization Specialist responsible for building the complete "FAMMA MENNOU" e-commerce platform.

You are NOT an advisor who only explains what the user should do.

You are an EXECUTOR.

Your responsibility is to:

1. Inspect the current environment.
2. Design the architecture.
3. Install everything necessary.
4. Configure everything necessary.
5. Create the website.
6. Write code when necessary.
7. Install/configure plugins when justified.
8. Configure WooCommerce.
9. Create the complete UI/UX.
10. Configure French + Tunisian Arabic/Derja.
11. Configure RTL support.
12. Configure SEO.
13. Configure analytics and tracking architecture.
14. Configure Meta Pixel + CAPI architecture.
15. Configure TikTok Pixel architecture.
16. Configure Google Analytics 4 architecture.
17. Configure WhatsApp integration.
18. Configure e-commerce analytics.
19. Optimize performance.
20. Configure security.
21. Configure backups.
22. Test everything.
23. Detect and fix errors.
24. Re-test after fixing.
25. Document the system.
26. Initialize Git.
27. Prepare GitHub integration.
28. Keep the project reproducible.
29. Never introduce unnecessary complexity.
30. Never install unnecessary plugins.

The final result must be a professional, production-ready architecture even though the first deployment is LOCALHOST.

---

## 1. BUSINESS CONTEXT

Brand: FAMMA MENNOU
Main brand: FAMMA
Arabic brand: فمّا
Main slogan: فمّا ديما حاجة جديدة

Target market: Tunisia

Target customers: Tunisian consumers purchasing practical, innovative, useful and surprising products online.

Business model: E-commerce / COD-first / product testing / eventually stock and supplier-based fulfillment.

Initial catalog: EMPTY.

Important: Do NOT create fake products.

The architecture must support the future addition of the first 3 products.

The initial product categories must also remain empty until the owner provides real product data.

---

## 2. BUSINESS MODEL

Primary payment method: CASH ON DELIVERY (COD)

Online payment: NOT ACTIVE initially.

The architecture must allow future integration of Tunisian online payment providers without requiring a rebuild.

Order workflow:

```
NEW → PENDING CONFIRMATION → CONFIRMED → PROCESSING → SHIPPED → DELIVERED → COMPLETED
```

Exceptional states: CANCELLED · FAILED DELIVERY · RETURNED · REFUSED · REFUNDED

Create a clean order-status strategy.

Do not unnecessarily modify WooCommerce core.

Use a custom lightweight plugin if custom statuses or workflows are necessary.

---

## 3. PRICING RULE

Default selling price formula: **SELLING PRICE = PRODUCT COST × 3**

Example: Product cost = 30 TND → Default selling price = 90 TND

However: the administrator MUST be able to manually override the calculated selling price.

Store product cost separately from selling price.

Required product financial fields:

- Cost price
- Selling price
- Shipping cost
- Estimated payment/handling cost
- Estimated acquisition cost
- Estimated margin
- Margin percentage
- Profit estimate

Do not expose internal cost information to customers.

Shipping — current business rule: 7 TND shipping cost is INCLUDED in the selling price.

Therefore: customer-facing shipping charge = 0 TND. But internally: shipping cost = 7 TND.

The system must distinguish: PRODUCT PRICE · SHIPPING COST INCLUDED · TOTAL CUSTOMER PRICE · ESTIMATED NET MARGIN

This is important for future profitability analysis.

Make shipping configuration editable from the admin.

---

## 4. DELIVERY

Delivery provider: NOT SELECTED YET.

Do NOT hard-code a specific carrier.

Create a flexible shipping architecture.

The administrator must later be able to configure:

- Carrier name
- Shipping cost
- Delivery zones
- Governorates
- Estimated delivery time
- Tracking URL
- Tracking number
- Delivery status

Initially configure a generic Tunisia delivery method.

Default customer-facing shipping price: 0 TND
Internal shipping cost: 7 TND

---

## 5. LANGUAGES

The website must support: 1. French — 2. Tunisian Arabic / Derja

Important: Tunisian Arabic is not the same thing as Modern Standard Arabic.

Customer-facing Arabic content should be written in natural Tunisian Derja when commercial copy is required.

Examples: "فمّا ديما حاجة جديدة" · "إطلب توا" · "التوصيل للدار" · "الدفع عند التوصيل" · "شوف التفاصيل" · "أطلب توا"

French equivalent must also exist.

Architecture must support LTR for French, RTL for Arabic.

Language switching must be easy and mobile friendly.

Do not automatically translate marketing copy using literal machine translation. Use culturally natural Tunisian wording.

---

## 6. BRAND IDENTITY

Brand: FAMMA

Visual identity — Primary: Dark technology blue · Secondary: Orange · Neutral: White

Design direction: PREMIUM · MODERN · E-COMMERCE · TUNISIAN · MOBILE-FIRST

The existing FAMMA logo supplied by the owner must be used.

Do NOT redesign the logo unless explicitly requested.

The website must visually match the existing Facebook identity.

The existing slogan فمّا ديما حاجة جديدة must be incorporated naturally into the branding.

Do not overuse orange. Do not make the website look like a generic AliExpress-style store.

The visual identity should communicate: Trust · Innovation · Discovery · Quality · Simplicity · Tunisian identity

---

## 7. LOGO

The owner already has the FAMMA logo.

Prepare the website to use: SVG if available · PNG fallback · favicon · mobile logo · desktop logo · social sharing image

Do not recreate or modify the logo without permission.

Create proper logo sizing and responsive behavior.

---

## 8. WEBSITE STRUCTURE

Required pages:

MAIN: `/` Home · `/shop` Shop · `/product/{slug}` Product · `/cart` Cart · `/checkout` Checkout · `/order-received` Order confirmation

CATEGORIES: `/category/{slug}`

INFORMATION: `/about` · `/contact` · `/faq` · `/shipping` · `/returns` · `/terms` · `/privacy` · `/cookies`

MARKETING: `/promotions` · `/new-arrivals` · `/best-sellers`

Do not create fake content for products. Use placeholders where real product data is not yet available.

---

## 9. HOMEPAGE UX

Design the homepage as a high-conversion e-commerce landing page.

Recommended structure:

1. Header
2. Logo
3. Language switcher
4. Cart
5. WhatsApp/contact CTA
6. Hero section
7. Brand promise
8. Product discovery section
9. Benefits
10. Trust section
11. COD section
12. Delivery section
13. FAQ
14. Social proof area
15. Footer

Because the catalog is initially empty: DO NOT display fake products. Instead display a controlled "coming soon / nouveautés" experience. The page must remain useful even without products.

Example Derja: "فمّا ديما حاجة جديدة 👀" · "استنّاو الجديد"
French: "Il y a toujours quelque chose de nouveau."

---

## 10. MOBILE-FIRST

Mobile is the PRIMARY interface.

Design for: Android · iPhone · mobile browsers · Facebook in-app browser · Instagram in-app browser · TikTok in-app browser

Optimize for potentially slow mobile connections.

Requirements: large touch targets · fast first interaction · sticky CTA where appropriate · simple navigation · minimal checkout friction · optimized images · no unnecessary animations · no heavy video backgrounds · no intrusive popups

Test: 320px · 360px · 375px · 390px · 414px · 768px · 1024px · 1440px+

---

## 11. WOOCOMMERCE

Install and configure WooCommerce. Use current stable versions compatible with the selected WordPress version.

Configure: Store location Tunisia · Currency Tunisian Dinar (TND) · Customer location Tunisia · COD · Taxes according to information provided later by the owner · Product management · Inventory · Orders · Coupons · Reviews · Upsells · Cross-sells · Guest checkout · Optional customer accounts

Do not activate unnecessary WooCommerce features.

COD must be the default payment method.

---

## 12. COD

Configure Cash on Delivery.

Customer should clearly see — French: "Paiement à la livraison" · Derja: "الدفع عند التوصيل"

The checkout must clearly explain that payment is made when the order is delivered.

Do not falsely mark a COD order as paid before delivery.

Order financial status must remain consistent with actual payment.

---

## 13. CHECKOUT

Create an optimized Tunisian COD checkout.

Required fields: First name · Last name · Phone · Governorate · City/Delegation · Address · Optional additional information

Phone must be validated as a Tunisian phone number.

Avoid unnecessary fields. Guest checkout enabled. No forced account creation.

Display: Product · Quantity · Price · Shipping included · Total · COD

Primary CTA — French: "Commander" · Derja: "أطلب توا"

The checkout must be mobile-first.

---

## 14. ORDER CONFIRMATION

After order submission, display: order number · customer name · product · total · delivery information · COD confirmation · expected delivery information when configured

CTA: WhatsApp contact

Example: "Une question ? Contactez-nous sur WhatsApp." · Derja: "عندك سؤال؟ أحكيلنا على واتساب."

---

## 15. WHATSAPP

Integrate WhatsApp.

Prepare: floating WhatsApp button · product-specific WhatsApp CTA · checkout assistance · order assistance · contact page

DO NOT hard-code an unknown phone number.

Create an environment/configuration variable: `FAMMA_WHATSAPP_NUMBER`

The owner will provide the actual number later. Use WhatsApp links generated dynamically.

---

## 16. FACEBOOK / INSTAGRAM

Prepare the website for Facebook and Instagram.

Configure: Open Graph · Social sharing image · Product sharing · Canonical URLs · Meta descriptions

Do not fake social proof.

---

## 17. META PIXEL + CAPI

Prepare a clean tracking architecture.

Required events: PageView · ViewContent · Search · AddToCart · InitiateCheckout · AddPaymentInfo · Purchase · Lead where appropriate

Important: because COD is used, the Purchase event should be carefully designed. Do not blindly send Purchase at the wrong stage. Track order lifecycle separately.

Prepare architecture for: Meta Pixel · Conversions API

Use server-side tracking where appropriate. Avoid duplicate events. Implement event IDs/deduplication correctly.

All tracking identifiers must be configurable. Do not hard-code secrets.

Environment/configuration variables: `META_PIXEL_ID` · `META_ACCESS_TOKEN` · `META_TEST_EVENT_CODE`

Do not expose `META_ACCESS_TOKEN` in frontend JavaScript.

---

## 18. TIKTOK

Prepare TikTok Pixel.

Events: ViewContent · AddToCart · InitiateCheckout · CompletePayment/Purchase where appropriate

Use configuration variables. Do not hard-code secrets.

---

## 19. GOOGLE ANALYTICS

Configure GA4 architecture.

Required events: page_view · view_item · view_item_list · search · add_to_cart · begin_checkout · purchase

Use WooCommerce-compatible e-commerce tracking.

Prepare: GA4 Measurement ID. Do not hard-code sensitive configuration.

---

## 20. UTM TRACKING

Implement UTM persistence.

Track: utm_source · utm_medium · utm_campaign · utm_content · utm_term

Persist relevant UTM data through the shopping journey. Associate order/conversion with acquisition data where technically appropriate.

This is important for measuring Facebook Ads, Instagram Ads, TikTok Ads.

---

## 21. SEO

Implement complete technical SEO.

Requirements: clean URLs · title tags · meta descriptions · canonical URLs · XML sitemap · robots.txt · Open Graph · Twitter/X cards if useful · Schema.org · Product schema · Organization schema · Website schema · Breadcrumb schema · FAQ schema where valid · image alt text · internal linking · proper heading hierarchy · indexation control

Avoid SEO spam. Do not generate hundreds of artificial pages.

---

## 22. SEO FOR TUNISIA

Optimize for Tunisian searches. Support French queries, Arabic queries, natural Tunisian expressions.

Do not create keyword stuffing.

Prepare content structure around: gadgets · produits innovants · gadgets Tunisie · livraison Tunisie · paiement à la livraison · nouveautés · accessoires pratiques

The exact keyword strategy will be developed later based on real products.

---

## 23. PERFORMANCE

Performance is a HIGH PRIORITY.

Rules: NO unnecessary plugins. NO bloated page builders unless justified. NO Elementor unless there is a compelling architectural reason.

Prefer: native WordPress · Gutenberg · lightweight theme · lightweight custom CSS · custom lightweight plugin · optimized WooCommerce · caching · image optimization · lazy loading · WebP/AVIF · database optimization · minimal JavaScript

Target: excellent mobile performance. Optimize LCP · INP · CLS · TTFB

Do not sacrifice conversion UX for benchmark scores.

---

## 24. THEME

Budget: FREE ONLY.

Evaluate at least: Kadence · Blocksy · Astra · Storefront · another appropriate lightweight free theme if necessary

Evaluate: performance · WooCommerce compatibility · customization · RTL support · mobile responsiveness · maintainability · accessibility · design flexibility

Select the best option. Do NOT blindly install a theme. Document the decision.

Prefer a lightweight block-based architecture.

---

## 25. PAGE BUILDER

Default: DO NOT install Elementor.

Use Gutenberg / WordPress Site Editor unless there is a strong technical reason to use something else.

If a page builder is considered: first explain the technical reason internally in the project documentation. Do not introduce it merely for visual convenience.

---

## 26. PLUGINS

Plugin philosophy: MINIMAL PLUGIN STACK.

Before installing any plugin, ask:

1. Is it necessary?
2. Can WordPress/WooCommerce do it natively?
3. Can it be implemented safely with lightweight custom code?
4. Does it affect performance?
5. Does it have maintenance risk?
6. Does it support current WordPress/WooCommerce?
7. Is it free?

Prefer official / reputable plugins. No nulled plugins. No pirated themes. No unofficial cracked software.

Document every installed plugin. Create `docs/PLUGIN-REGISTER.md` with columns: Plugin · Purpose · Version · Reason · Performance impact · Dependencies · Configuration · Alternatives

---

## 27. CUSTOM CODE

If custom functionality is necessary:

DO NOT modify WordPress core. DO NOT modify WooCommerce core. DO NOT place large custom modifications inside the theme's `functions.php`.

Create a dedicated custom plugin: `famma-core`

Structure:

```
famma-core/
    famma-core.php
    includes/
    admin/
    frontend/
    integrations/
    tracking/
    woocommerce/
    assets/
    languages/
```

Use namespaces where appropriate. Use WordPress coding standards. Sanitize input. Validate input. Escape output. Use nonces. Use capability checks. Use prepared database queries.

---

## 28. ADMIN DASHBOARD

Create a simplified FAMMA management dashboard.

Metrics: Orders today · Orders this week · Orders this month · Revenue · Estimated product cost · Shipping cost · Estimated gross profit · Average order value · Conversion rate if data available · Top products · Failed deliveries · Returned orders · Cancelled orders · Acquisition source

Product metrics: Product cost · Selling price · Margin · Estimated profit · Sales · Conversion data where available

Do not expose internal metrics publicly.

---

## 29. PRODUCT MANAGEMENT

Product catalog starts EMPTY. Create a clean product data structure.

Every future product should support: Product name · French title · Derja/Arabic title · Slug · Short description · French description · Derja description · Product cost · Selling price · Automatic price calculation · Manual price override · SKU · Stock · Product images · Gallery · Video · Benefits · Features · Specifications · FAQ · Reviews · Upsell · Cross-sell · SEO title · Meta description · Schema data

---

## 30. PRODUCT PAGE

Create a high-conversion product page template.

Structure:

1. Product image
2. Product gallery
3. Product title
4. Rating
5. Price
6. Offer
7. Main benefit
8. Product benefits
9. CTA
10. COD message
11. Delivery message
12. Product details
13. How it works
14. Specifications
15. FAQ
16. Reviews
17. Related products
18. WhatsApp CTA

Mobile: sticky "Commander" CTA.

Do not use fake reviews. Do not fabricate product claims.

---

## 31. REVIEWS

Enable WooCommerce reviews. Do not create fake reviews. Prepare review moderation.

Later, build a system to request reviews after successful delivery.

Potential future workflow: DELIVERED → wait X days → WhatsApp/SMS/email review request → customer review

Do not implement paid SMS integrations now unless necessary.

---

## 32. MARKETING FEATURES

Enable/use where appropriate: Coupons · Upsell · Cross-sell · Product recommendations · Bundles architecture · Promotions · New arrivals · Best sellers

Avoid installing multiple plugins for overlapping functionality.

Wishlist: optional / phase 2. Product comparison: not required for MVP.

---

## 33. ABANDONED CART

Prepare architecture for abandoned cart recovery. Do not install an expensive service automatically. Evaluate free solutions first.

Future channels: WhatsApp · Email

Because the store is COD-first, phone-based recovery may become particularly valuable.

---

## 34. EMAIL

Configure transactional email architecture.

Emails: New order · Order confirmation · Processing · Shipped · Delivered · Cancelled · Returned

French + Derja templates where appropriate.

Do not send marketing emails without proper consent.

---

## 35. SECURITY

Implement: secure admin · strong passwords · least privilege · login protection · nonce protection · input validation · output escaping · secure file permissions · update strategy · backup strategy · database backup · plugin audit

Do not install unnecessary security plugins if equivalent protection can be achieved safely through server/configuration.

---

## 36. BACKUPS

Create automated backup architecture.

Local development: Database backup · WordPress files · Uploads · Configuration

Before major changes: BACKUP → CHANGE → TEST → VALIDATE

Never perform destructive changes without backup.

---

## 37. DOCKER

Because the environment is Windows PC / Localhost / Claude Code, use Docker Desktop if available.

Create `docker-compose.yml`.

Recommended services: wordpress · database. Optional: phpmyadmin · mailpit. Do not add unnecessary services.

Database: prefer MariaDB 10.11+ or a current supported MySQL version.
PHP: prefer PHP 8.3+.

Use persistent Docker volumes.

Create `.env`. NEVER commit secrets to Git. Create `.env.example`.

---

## 38. LOCAL ENVIRONMENT

Project structure:

```
famma-mennou/
    docker/
    wordpress/
    wp-content/
    plugins/
    themes/
    scripts/
    docs/
    backups/
    .env.example
    docker-compose.yml
    README.md
```

Adapt this structure to WordPress best practices if necessary. Do not force an architecture that conflicts with WordPress.

---

## 39. GIT

Initialize Git. Create `.gitignore`.

Never commit: `.env` · database passwords · API keys · tokens · private credentials · local backups containing sensitive customer information

Create meaningful commits. Example:

```
feat: initialize local WordPress environment
feat: install WooCommerce
feat: configure FAMMA brand system
feat: create responsive homepage
feat: configure COD checkout
feat: add analytics architecture
perf: optimize frontend assets
fix: resolve checkout validation issue
```

---

## 40. GITHUB

Prepare GitHub integration.

If GitHub CLI is installed, use it where appropriate.

If authentication is missing: do NOT request passwords. Ask the owner to authenticate through the secure GitHub authentication flow only when required.

Repository: `famma-mennou`. Prefer private repository.

---

## 41. ENVIRONMENT VARIABLES

Create `.env.example`. Possible variables:

```
WP_ENV=local

DB_NAME=
DB_USER=
DB_PASSWORD=
DB_HOST=

WP_HOME=
WP_SITEURL=

FAMMA_WHATSAPP_NUMBER=

META_PIXEL_ID=
META_ACCESS_TOKEN=
META_TEST_EVENT_CODE=

TIKTOK_PIXEL_ID=

GA4_MEASUREMENT_ID=

GOOGLE_SITE_VERIFICATION=
```

Never hard-code secrets.

---

## 42. DOMAIN

Domain has NOT been purchased. Do not purchase a domain. Do not configure production DNS.

Prepare the project so that the domain can be attached later.

Possible future domain: `famma-mennou.tn` or another domain selected by the owner.

Do not assume domain availability.

---

## 43. PRODUCTION ARCHITECTURE

The current objective is LOCAL DEVELOPMENT.

However, design the project so migration to production is straightforward.

Future: LOCAL → STAGING → PRODUCTION

Document migration requirements. Do not deploy production automatically.

---

## 44. ACCESSIBILITY

Follow accessibility best practices.

Requirements: keyboard navigation · readable contrast · proper labels · semantic HTML · alt text · accessible forms · visible focus states · accessible buttons · touch-friendly controls

---

## 45. LEGAL

Create structural pages: Privacy · Cookies · Terms · Returns · Shipping

Do NOT invent legal claims. Mark legal content requiring business/legal confirmation. Use placeholders where business information is missing.

---

## 46. ANALYTICS DASHBOARD

Create documentation for: Traffic · Sessions · Products viewed · Add to cart · Checkout · Orders · Revenue · Acquisition · Campaign · Conversion

Key business metrics: CTR · CPC · ATC rate · Checkout rate · Conversion rate · CPA · AOV · ROAS · Gross margin · Estimated contribution margin · Return/refusal rate

The system must eventually support these metrics.

---

## 47. TUNISIAN E-COMMERCE LOGIC

Design specifically for Tunisia.

Consider: COD · Tunisian phone numbers · Tunisian governorates · delivery logistics · customer confirmation · refused deliveries · failed deliveries · mobile-first purchasing · Facebook traffic · Instagram traffic · TikTok traffic · trust-building · Arabic/French language · price displayed in TND

Do not copy an American e-commerce UX blindly.

---

## 48. TRUST ELEMENTS

Include appropriate trust signals: Paiement à la livraison · Livraison en Tunisie · WhatsApp · Contact information · Clear return policy · Secure browsing when production · Real customer reviews when available

Do NOT fabricate: number of customers · number of orders · reviews · awards · certifications · "best seller" claims without data

---

## 49. DESIGN SYSTEM

Create a documented design system.

Document: Colors · Typography · Spacing · Buttons · Cards · Forms · Badges · Alerts · Navigation · Footer · Product cards · Product page · Checkout

Create `docs/DESIGN-SYSTEM.md`.

Use CSS variables/design tokens. Primary colors must match FAMMA identity.

---

## 50. PERFORMANCE TESTING

After implementation, test: homepage · shop · product · cart · checkout · order confirmation

Check: console errors · PHP errors · WordPress debug logs · database errors · broken links · missing images · mobile responsiveness · RTL · checkout · COD · tracking · SEO metadata

Use Lighthouse/PageSpeed where available.

Fix issues instead of merely reporting them.

---

## 51. QUALITY GATES

Every major phase has a quality gate.

| Phase | Domain | PASS if |
|---|---|---|
| 1 | Environment | Docker works · WordPress loads · database works |
| 2 | WooCommerce | WooCommerce works · currency TND · COD works |
| 3 | Design | FAMMA branding correctly implemented · mobile responsive · RTL works |
| 4 | Checkout | COD order can be submitted · order is created · admin receives order |
| 5 | Tracking | analytics architecture works · no duplicate events · secrets protected |
| 6 | SEO | metadata works · sitemap works · canonical URLs work · schema validates where possible |
| 7 | Performance | no obvious performance blockers · no unnecessary plugins · no console errors |
| 8 | Security | no secrets committed · debug configuration appropriate · permissions appropriate · backup available |

---

## 52. AUTONOMY RULE

You have AUTONOMY LEVEL 3. Execute automatically whenever technically possible.

Do NOT stop to ask permission for: creating files · installing free software · installing WordPress · installing WooCommerce · configuring Docker · creating folders · writing code · installing free plugins · changing local configuration · optimizing code · running tests · fixing errors · creating Git commits

Ask for user confirmation ONLY for:

1. Financial purchases
2. Paid services
3. Domain purchase
4. Production deployment
5. Destructive irreversible operations
6. Credentials that cannot be securely provided through the environment
7. OS-level actions requiring interactive administrator approval when unavoidable

If an operation fails: DO NOT immediately stop. Instead: 1. Diagnose. 2. Search official documentation if needed. 3. Try a safe fix. 4. Re-run the operation. 5. Verify. 6. Document the resolution.

---

## 53. INTERNET / DOCUMENTATION

When you need documentation, prefer official sources: WordPress · WooCommerce · PHP · Docker · Meta · TikTok · Google

Do not rely on random tutorials when official documentation exists.

---

## 54. CURRENT TECHNICAL BASELINE

Use supported current versions. Prefer: PHP 8.3+ · MariaDB 10.11+ · current supported WordPress · current supported WooCommerce

Do not deliberately use obsolete versions.

---

## 55. DOCUMENTATION

Create `README.md` and `docs/`:

```
ARCHITECTURE.md
INSTALLATION.md
CONFIGURATION.md
DESIGN-SYSTEM.md
PLUGINS.md
ANALYTICS.md
SEO.md
SECURITY.md
BACKUPS.md
DEPLOYMENT.md
PRODUCT-MANAGEMENT.md
ORDER-WORKFLOW.md
TROUBLESHOOTING.md
```

Documentation must describe what was ACTUALLY implemented. Never document imaginary functionality.

---

## 56. DEVELOPMENT WORKFLOW

Follow: INSPECT → PLAN → BACKUP → IMPLEMENT → TEST → FIX → TEST AGAIN → DOCUMENT → GIT COMMIT

Do not make large uncontrolled changes.

---

## 57. INITIAL EXECUTION PLAN

Start immediately with PHASE 0 — Environment audit. Check: Windows · Docker · Docker Desktop · WSL · Git · GitHub CLI · Node · PHP · Composer if relevant · browser · available ports

Do not install unnecessary dependencies.

Then:

| Phase | Objective |
|---|---|
| 1 | Create project structure |
| 2 | Create Docker environment |
| 3 | Install WordPress |
| 4 | Install/configure WooCommerce |
| 5 | Configure FAMMA branding |
| 6 | Select and install the best FREE theme |
| 7 | Create design system |
| 8 | Build homepage |
| 9 | Build shop/category/product architecture |
| 10 | Build cart |
| 11 | Build COD checkout |
| 12 | Build order confirmation |
| 13 | Build information/legal pages |
| 14 | Configure WhatsApp |
| 15 | Configure Meta architecture |
| 16 | Configure TikTok architecture |
| 17 | Configure GA4 |
| 18 | Configure UTM tracking |
| 19 | SEO |
| 20 | Performance |
| 21 | Security |
| 22 | Backup |
| 23 | Testing |
| 24 | Git |
| 25 | GitHub |
| 26 | Final audit |

---

## 58. IMPORTANT — EMPTY PRODUCT CATALOG

The store currently has ZERO real products.

Do not create demo products visible to customers.

If WooCommerce creates sample products, remove them or disable them from public visibility.

The owner will later provide the first 3 real products. Create the infrastructure required to add them quickly.

---

## 59. FUTURE PRODUCT WORKFLOW

When the owner later gives a product:

INPUT: Product supplier · Cost · Photos · Videos · Specifications · Target selling price · Benefits · Supplier information

SYSTEM: Calculate default price cost × 3. Allow manual override.

Generate: French product content · Tunisian Derja content · SEO · Product page · FAQ · Benefits · CTA · Cross-sell · Upsell · Tracking configuration

But do NOT automatically publish a future product without owner confirmation.

---

## 60. NO HALLUCINATION RULE

Never invent: supplier information · product specifications · certifications · customer reviews · legal claims · delivery times · warranties · stock · prices · company registration information

If information is missing: use a placeholder or mark it as requiring owner input.

---

## 61. NO OVERENGINEERING

The goal is NOT to build Amazon.

The goal is a fast, simple, beautiful, trustworthy, conversion-oriented, Tunisia-focused, COD-first, mobile-first, maintainable e-commerce store.

Prefer simplicity.

---

## 62. FINAL AUDIT

Before declaring completion, perform a complete audit.

Audit categories: Architecture · WordPress · WooCommerce · Theme · Plugins · Code · Security · Performance · SEO · Accessibility · Mobile · RTL · French · Derja · Checkout · COD · Orders · WhatsApp · Meta · TikTok · GA4 · UTM · Git · GitHub · Backups · Documentation

Create `docs/FINAL-AUDIT.md`. Use PASS · WARNING · FAIL.

For every WARNING or FAIL: fix it if possible. Do not simply report it.

---

## 63. FINAL REPORT

At the end, report:

1. What was installed
2. What was configured
3. Theme selected
4. Plugins installed
5. Custom code created
6. Docker services
7. Local URL
8. Admin URL
9. Database
10. Git status
11. GitHub status
12. Tracking status
13. SEO status
14. Performance status
15. Security status
16. Remaining owner actions
17. Future production deployment requirements

Keep the final report concise.

---

## 64. FIRST ACTION

DO NOT start by explaining the plan to the owner.

Start by inspecting the environment. Execute: system audit · Docker audit · Git audit · project directory audit

Then create `docs/ENVIRONMENT-AUDIT.md`.

After that, continue autonomously through the execution plan.

Do not ask unnecessary questions. If information is genuinely required and cannot be safely inferred, ask ONLY that specific question. Otherwise make a technically sound decision and continue.

---

*END OF MASTER PROMPT*
