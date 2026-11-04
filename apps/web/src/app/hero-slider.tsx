"use client";

import { useEffect, useState } from "react";

const BASE = process.env.NEXT_PUBLIC_BASE_PATH ?? "";

const SLIDES = [
  { img: `${BASE}/assets/slider/1.jpg`, kicker: "Tarih Kokan", title: "İSTANBUL" },
  { img: `${BASE}/assets/slider/2.jpg`, kicker: "Ebedi Mavilikler Ülkesi", title: "BODRUM" },
  { img: `${BASE}/assets/slider/3.jpg`, kicker: "Periler Şehri", title: "KAPADOKYA" },
  { img: `${BASE}/assets/slider/4.jpg`, kicker: "Işıklar Şehri", title: "BAKÜ" },
];

// Otomatik kayan, yazıları soldan animasyonla gelen ana ekran.
export function HeroSlider({ ctaHref }: { ctaHref: string }) {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    const timer = setInterval(() => setIndex((i) => (i + 1) % SLIDES.length), 6000);
    return () => clearInterval(timer);
  }, [index]);

  const go = (i: number) => setIndex((i + SLIDES.length) % SLIDES.length);

  return (
    <section className="slider" aria-roledescription="carousel" aria-label="Öne çıkan şehirler">
      {SLIDES.map((s, i) => (
        <div
          key={s.title}
          className={`slide${i === index ? " active" : ""}`}
          style={{ backgroundImage: `url(${s.img})` }}
          aria-hidden={i !== index}
        >
          {i === index && (
            <div className="slide-text">
              <h6 style={{ animationDelay: "200ms" }}>{s.kicker}</h6>
              <h2 style={{ animationDelay: "500ms" }}>{s.title}</h2>
              <h3 style={{ animationDelay: "800ms" }}>En İyi Fiyat Garanti</h3>
              <a className="btn" href={ctaHref} style={{ animationDelay: "1100ms" }}>
                Rezervasyon Yapın
              </a>
            </div>
          )}
        </div>
      ))}
      <button className="slider-arrow prev" onClick={() => go(index - 1)} aria-label="Önceki">
        ‹
      </button>
      <button className="slider-arrow next" onClick={() => go(index + 1)} aria-label="Sonraki">
        ›
      </button>
      <div className="slider-dots">
        {SLIDES.map((s, i) => (
          <button key={s.title} className={i === index ? "on" : ""} onClick={() => go(i)} aria-label={s.title} />
        ))}
      </div>
    </section>
  );
}
