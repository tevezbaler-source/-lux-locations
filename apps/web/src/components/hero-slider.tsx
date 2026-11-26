"use client";

import { useEffect, useState } from "react";
import type { Dict } from "@/i18n/types";

const BASE = process.env.NEXT_PUBLIC_BASE_PATH ?? "";

const IMAGES = [1, 2, 3, 4].map((n) => `${BASE}/assets/slider/${n}.jpg`);

// Otomatik kayan, yazıları soldan animasyonla gelen ana ekran. Metinler seçili dilden gelir.
export function HeroSlider({ ctaHref, t }: { ctaHref: string; t: Dict["slider"] }) {
  const [index, setIndex] = useState(0);
  const SLIDES = t.slides.map((s, i) => ({ ...s, img: IMAGES[i] }));

  useEffect(() => {
    const timer = setInterval(() => setIndex((i) => (i + 1) % SLIDES.length), 6000);
    return () => clearInterval(timer);
  }, [index, SLIDES.length]);

  const go = (i: number) => setIndex((i + SLIDES.length) % SLIDES.length);

  return (
    <section className="slider" aria-roledescription="carousel" aria-label={t.label}>
      {SLIDES.map((s, i) => (
        <div
          key={s.img}
          className={`slide${i === index ? " active" : ""}`}
          style={{ backgroundImage: `url(${s.img})` }}
          aria-hidden={i !== index}
        >
          {i === index && (
            <div className="slide-text">
              <h6 style={{ animationDelay: "200ms" }}>{s.kicker}</h6>
              <h2 style={{ animationDelay: "500ms" }}>{s.title}</h2>
              <h3 style={{ animationDelay: "800ms" }}>{t.bestPrice}</h3>
              <a className="btn" href={ctaHref} style={{ animationDelay: "1100ms" }}>
                {t.book}
              </a>
            </div>
          )}
        </div>
      ))}
      <button className="slider-arrow prev" onClick={() => go(index - 1)} aria-label={t.prev}>
        ‹
      </button>
      <button className="slider-arrow next" onClick={() => go(index + 1)} aria-label={t.next}>
        ›
      </button>
      <div className="slider-dots">
        {SLIDES.map((s, i) => (
          <button key={s.img} className={i === index ? "on" : ""} onClick={() => go(i)} aria-label={s.title} />
        ))}
      </div>
    </section>
  );
}
