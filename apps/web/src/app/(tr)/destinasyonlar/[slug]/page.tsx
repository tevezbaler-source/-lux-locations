import { notFound } from "next/navigation";
import { DEST_SLUGS, DestinationView, destinationMetadata, isDestSlug } from "@/views/destination";

export const dynamicParams = false;
export const generateStaticParams = () => DEST_SLUGS.map((slug) => ({ slug }));

async function slugOf(params: Promise<{ slug: string }>) {
  const { slug } = await params;
  if (!isDestSlug(slug)) notFound();
  return slug;
}

export async function generateMetadata({ params }: PageProps<"/destinasyonlar/[slug]">) {
  return destinationMetadata("tr", await slugOf(params));
}

export default async function Page({ params }: PageProps<"/destinasyonlar/[slug]">) {
  return <DestinationView lang="tr" slug={await slugOf(params)} />;
}
