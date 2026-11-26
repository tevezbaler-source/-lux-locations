import { notFound } from "next/navigation";
import { isServiceSlug, SERVICE_SLUGS, ServiceView, serviceMetadata } from "@/views/service";

export const dynamicParams = false;
export const generateStaticParams = () => SERVICE_SLUGS.map((slug) => ({ slug }));

async function slugOf(params: Promise<{ slug: string }>) {
  const { slug } = await params;
  if (!isServiceSlug(slug)) notFound();
  return slug;
}

export async function generateMetadata({ params }: PageProps<"/hizmetler/[slug]">) {
  return serviceMetadata("tr", await slugOf(params));
}

export default async function Page({ params }: PageProps<"/hizmetler/[slug]">) {
  return <ServiceView lang="tr" slug={await slugOf(params)} />;
}
