import "../globals.css";
import { RootHtml } from "@/views/root-layout";

// Türkçe sayfalar kökte (/) kalır; eski bağlantılar değişmez.
export default function TurkishLayout({ children }: { children: React.ReactNode }) {
  return <RootHtml lang="tr">{children}</RootHtml>;
}
