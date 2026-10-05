import path from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = path.dirname(fileURLToPath(import.meta.url));

/** @type {import('next').NextConfig} */
const nextConfig = {
  async redirects() {
    return [
      {
        source: "/w/:path*",
        destination: "/:path*",
        permanent: true,
      },
    ];
  },
  devIndicators: {
    position: "bottom-right",
  },
  turbopack: {
    root: projectRoot,
  },
};

export default nextConfig;
