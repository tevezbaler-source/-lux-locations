import { HomeView, homeMetadata } from "@/views/home";
import { toLocale } from "./params";

export async function generateMetadata({ params }: PageProps<"/[lang]">) {
  return homeMetadata(toLocale((await params).lang));
}

export default async function Page({ params }: PageProps<"/[lang]">) {
  return <HomeView lang={toLocale((await params).lang)} />;
}
