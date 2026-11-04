import type { NextConfig } from "next";

// `npm run export` (veya STATIC_EXPORT=1): paylaşımlı Linux hostinge (FTP) yüklenecek statik çıktı (out/).
// Statik dışa aktarma PPR / Cache Components ile birlikte çalışmaz.
const staticExport = process.env.STATIC_EXPORT === "1" || process.env.npm_lifecycle_event === "export";

const nextConfig: NextConfig = staticExport
  ? {
      output: "export",
      trailingSlash: true,
      images: { unoptimized: true },
      transpilePackages: ["@lux/shared"],
    }
  : {
      cacheComponents: true,
      partialPrefetching: true,
      transpilePackages: ["@lux/shared"],
    };

export default nextConfig;
