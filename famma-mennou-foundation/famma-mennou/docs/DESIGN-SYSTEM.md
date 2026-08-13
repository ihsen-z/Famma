# Design system (§6, §49)

Premium · modern · Tunisian · mobile-first. Not AliExpress-generic. Orange is an
accent, never the dominant colour.

## Design tokens (CSS variables)
```css
:root{
  /* Brand */
  --famma-blue:      #0B1F3A;  /* primary — dark tech blue */
  --famma-blue-600:  #12315C;
  --famma-orange:    #FF7A00;  /* accent only — CTAs, highlights */
  --famma-white:     #FFFFFF;

  /* Neutrals */
  --famma-ink:       #12161C;
  --famma-muted:     #5B6470;
  --famma-line:      #E6E9EE;
  --famma-bg:        #F7F8FA;

  /* Feedback */
  --famma-success:   #1FA65A;  /* COD / delivered */
  --famma-warning:   #E6A700;
  --famma-danger:    #D64545;

  /* Type */
  --famma-font: system-ui, "Segoe UI", Tahoma, "Noto Sans Arabic", sans-serif;
  --famma-radius: 12px;
  --famma-shadow: 0 4px 14px rgba(11,31,58,.10);

  /* Spacing scale */
  --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-6:24px; --sp-8:32px;
}
```

## Primary CTA
Background `--famma-orange`, white text, `--famma-radius`, min touch target
48×48px. Label: FR **"Commander"** / Derja **"أطلب توا"**. On product + checkout,
sticky at the bottom on mobile (§13, §30).

## Trust chips (reused across pages) — §48
- 🚚 FR "Livraison en Tunisie" / Derja "التوصيل للدار"
- 💵 FR "Paiement à la livraison" / Derja "الدفع عند التوصيل"
- 💬 WhatsApp support

## RTL
Arabic renders RTL; keep layout logical-property based (`margin-inline`,
`padding-inline`, `inset-inline`) so LTR/RTL share one stylesheet. Test both.

## Empty-catalog homepage (§9, §58)
No fake products. Show a controlled "nouveautés / استنّاو الجديد" state:
- Hero with logo + slogan **فمّا ديما حاجة جديدة**
- Brand promise, COD + delivery trust sections, FAQ, WhatsApp CTA
FR fallback: *"Il y a toujours quelque chose de nouveau."*

## Breakpoints to test (§10)
320 · 360 · 375 · 390 · 414 · 768 · 1024 · 1440+
