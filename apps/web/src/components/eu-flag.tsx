const BASE = process.env.NEXT_PUBLIC_BASE_PATH ?? "";
const SLICES = 24;

// Ana ekranın köşesinde dalgalanan AB bayrağı (yazısız; label yalnız ekran okuyucu için). Bayrak dikey şeritlere bölünür;
// her şerit biraz gecikmeyle inip kalkar, gölgesi de birlikte değişir (yalnız transform/opacity, telefonda hafif).
export function EuFlag({ label }: { label: string }) {
  return (
    <div className="eu-flag">
      <div className="eu-flag-cloth" role="img" aria-label={label} style={{ ["--eu-img" as string]: `url(${BASE}/assets/eu-flag.svg)` }}>
        {Array.from({ length: SLICES }, (_, i) => (
          <span
            key={i}
            style={{
              ["--i" as string]: i,
              ["--a" as string]: (0.25 + (0.75 * i) / (SLICES - 1)).toFixed(3),
            }}
          />
        ))}
      </div>
    </div>
  );
}
