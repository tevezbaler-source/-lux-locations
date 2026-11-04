import "../globals.css";
import { RootHtml } from "@/views/root-layout";
import { generateLangParams, toLocale } from "./params";

export const dynamicParams = false;
export const generateStaticParams = generateLangParams;

export default async function LocaleLayout({ children, params }: LayoutProps<"/[lang]">) {
  return <RootHtml lang={toLocale((await params).lang)}>{children}</RootHtml>;
}
