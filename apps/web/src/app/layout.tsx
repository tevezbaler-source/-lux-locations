import type { Metadata } from "next";
import { DM_Sans, Playfair_Display } from "next/font/google";
import "./globals.css";

const sans = DM_Sans({
  variable: "--font-sans",
  subsets: ["latin", "latin-ext"],
});

const serif = Playfair_Display({
  variable: "--font-serif",
  subsets: ["latin", "latin-ext"],
  style: ["normal", "italic"],
});

export const metadata: Metadata = {
  title: "Lux Locations | Yolculuğunuz, size özel.",
  description:
    "İstanbul merkezli Lux Locations ile özel turlar, transfer, oteller, organizasyon, kiralama ve rehberlik hizmetleri için size özel teklif alın.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="tr" className={`${sans.variable} ${serif.variable}`}>
      <body>{children}</body>
    </html>
  );
}
