import { HomeView, homeMetadata } from "@/views/home";

export const metadata = homeMetadata("tr");

export default function Page() {
  return <HomeView lang="tr" />;
}
