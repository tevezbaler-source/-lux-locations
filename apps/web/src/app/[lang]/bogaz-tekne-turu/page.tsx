import { BosphorusView, bosphorusMetadata } from "@/views/bosphorus";
import { toLocale } from "../params";

export async function generateMetadata({ params }: PageProps<"/[lang]/bogaz-tekne-turu">) {
  return bosphorusMetadata(toLocale((await params).lang));
}

export default async function Page({ params }: PageProps<"/[lang]/bogaz-tekne-turu">) {
  return <BosphorusView lang={toLocale((await params).lang)} />;
}
