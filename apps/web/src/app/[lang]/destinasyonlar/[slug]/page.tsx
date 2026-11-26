import { notFound } from "next/navigation";
import { DEST_SLUGS, DestinationView, destinationMetadata, isDestSlug } from "@/views/destination";
import { toLocale } from "../../params";

export const dynamicParams = false;
export const generateStaticParams = () => DEST_SLUGS.map((slug) => ({ slug }));

async function read(params: Promise<{ lang: string; slug: string }>) {
  const { lang, slug } = await params;
  if (!isDestSlug(slug)) notFound();
  return { lang: toLocale(lang), slug };
}

export async function generateMetadata({ params }: PageProps<"/[lang]/destinasyonlar/[slug]">) {
  const { lang, slug } = await read(params);
  return destinationMetadata(lang, slug);
}

export default async function Page({ params }: PageProps<"/[lang]/destinasyonlar/[slug]">) {
  const { lang, slug } = await read(params);
  return <DestinationView lang={lang} slug={slug} />;
}
