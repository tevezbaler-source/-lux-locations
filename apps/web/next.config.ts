import type { NextConfig } from "next";

// STATIC_EXPORT=1 veya `npm run export` ile statik site üretilir (PHP/Linux hosting için). BASE_PATH alt klasörü belirler.
// Statik dışa aktarma PPR / Cache Components ile birlikte çalışmaz.
const staticExport = process.env.STATIC_EXPORT === "1" || process.env.npm_lifecycle_event === "export";

const nextConfig: NextConfig = {
  ...(!staticExport && { cacheComponents: true, partialPrefetching: true }),
  transpilePackages: ["@lux/shared"],
  ...(staticExport && {
    output: "export",
    trailingSlash: true,
    images: { unoptimized: true },
    basePath: process.env.BASE_PATH ?? "",
  }),
};

export default nextConfig;
