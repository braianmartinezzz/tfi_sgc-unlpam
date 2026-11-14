import type { ReactNode } from "react";
import AuthGuard from "@/components/layout/AuthGuard";
import Header from "@/components/layout/Header";
import OfflineBanner from "@/components/layout/OfflineBanner";
import Sidebar from "@/components/layout/Sidebar";

export default function PrivateLayout({ children }: { children: ReactNode }) {
  return (
    <AuthGuard>
      <div className="flex min-h-full flex-1 flex-col md:flex-row">
        <Sidebar />
        <div className="min-w-0 flex-1">
          <Header />
          <OfflineBanner />
          <main className="p-6">{children}</main>
        </div>
      </div>
    </AuthGuard>
  );
}
