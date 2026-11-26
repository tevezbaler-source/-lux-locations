import { notFound } from "next/navigation";
import { isServiceSlug, SERVICE_SLUGS, ServiceView, serviceMetadata } from "@/views/service";
import { toLocale } from "../../params";

export const dynamicParams = false;
export const generateStaticParams = () => SERVICE_SLUGS.map((slug) => ({ slug }));

async function read(params: Promise<{ lang: string; slug: string }>) {
  const { lang, slug } = await params;
  if (!isServiceSlug(slug)) notFound();
  return { lang: toLocale(lang), slug };
}

export async function generateMetadata({ params }: PageProps<"/[lang]/hizmetler/[slug]">) {
  const { lang, slug } = await read(params);
  return serviceMetadata(lang, slug);
}

export default async function Page({ params }: PageProps<"/[lang]/hizmetler/[slug]">) {
  const { lang, slug } = await read(params);
  return <ServiceView lang={lang} slug={slug} />;
}
