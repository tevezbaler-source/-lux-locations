import type { MetadataRoute } from "next";
import { SITE_URL } from "@/views/seo";

export const dynamic = "force-static";

// Arama motorlarına: sayfaların hepsi açık, yönetim ekranları kapalı.
// sitemap.xml sunucuda lx-content/sitemap.php ile üretilir (panelden eklenen oteller de içinde).
export default function robots(): MetadataRoute.Robots {
  return {
    rules: [{ userAgent: "*", allow: "/", disallow: ["/admin/", "/panel/", "/lx-content/data/"] }],
    sitemap: `${SITE_URL}/sitemap.xml`,
    host: SITE_URL,
  };
}
