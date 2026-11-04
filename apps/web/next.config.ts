import type { NextConfig } from "next";

// STATIC_EXPORT=1 ile statik site Ã¼retilir (PHP/Linux hosting iÃ§in). BASE_PATH alt klasÃ¶rÃ¼ belirler.
const staticExport = process.env.STATIC_EXPORT === "1";

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
