import { ar } from "./dict/ar";
import { de } from "./dict/de";
import { en } from "./dict/en";
import { es } from "./dict/es";
import { fa } from "./dict/fa";
import { fr } from "./dict/fr";
import { it } from "./dict/it";
import { ro } from "./dict/ro";
import { ru } from "./dict/ru";
import { tr } from "./dict/tr";
import { zh } from "./dict/zh";
import type { Locale } from "./locales";
import type { Dict } from "./types";

const DICTS: Record<Locale, Dict> = { tr, en, ar, ru, de, fr, es, it, zh, fa, ro };

export function getDict(lang: Locale): Dict {
  return DICTS[lang];
}

export * from "./locales";
export { fill, type Dict, type FormText } from "./types";
