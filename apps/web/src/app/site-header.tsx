import { RESERVATIONS } from "@lux/shared";

// Kaydırırken ekranla birlikte gelen (sticky) üst şerit + altındaki gezinme satırı.
const NAV = [
  { href: "/oteller", label: "Oteller" },
  { href: "/#organizasyon", label: "Organizasyonlar" },
  { href: "/bogaz-tekne-turu", label: "Boğaz Tekne Turu" },
  { href: "/#tur", label: "Özel Turlar" },
  { href: "/#transfer", label: "Transfer" },
  { href: "/#kiralama", label: "Kiralama" },
  { href: "/#rehberlik", label: "Rehberlik" },
  { href: "/#hakkimizda", label: "Hakkımızda" },
  { href: "/#iletisim", label: "İletişim" },
];

export function SiteHeader({ ctaHref, ctaLabel }: { ctaHref: string; ctaLabel: string }) {
  return (
    <header className="site-head">
      <div className="site-bar">
        <a className="brand" href="/">
          <img src="/assets/logo-mark.png" alt="" width={56} height={56} />
          <span>
            LUX
            <small>LOCATIONS TOURS</small>
          </span>
        </a>
        <a className="btn" href={ctaHref}>
          {ctaLabel}
        </a>
      </div>
      <nav className="site-nav" aria-label="Ana gezinme">
        {NAV.map((item) => (
          <a key={item.href} href={item.href}>
            {item.label}
          </a>
        ))}
      </nav>
      <div className="site-sub">
        {RESERVATIONS.map((r) => (<a key={r.id} href={`/${r.slug}`}>{r.title}</a>))}
      </div>
    </header>
  );
}
