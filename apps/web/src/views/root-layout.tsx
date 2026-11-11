import { DM_Sans, Playfair_Display } from "next/font/google";
import { localeDir, type Locale } from "@/i18n";

const sans = DM_Sans({
  variable: "--font-sans",
  subsets: ["latin", "latin-ext"],
});

const serif = Playfair_Display({
  variable: "--font-serif",
  subsets: ["latin", "latin-ext", "cyrillic"],
  style: ["normal", "italic"],
});

// Türkçe ve diğer diller için iki ayrı kök düzen var; ikisi de bu iskeleti kullanır.
export function RootHtml({ lang, children }: { lang: Locale; children: React.ReactNode }) {
  return (
    <html lang={lang} dir={localeDir(lang)} className={`${sans.variable} ${serif.variable}`}>
      <body>{children}</body>
    </html>
  );
}
