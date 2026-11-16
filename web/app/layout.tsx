import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { Roboto_Condensed } from "next/font/google";
import Providers from "@/context/Providers";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const robotoCondensed = Roboto_Condensed({
  variable: "--font-roboto-condensed",
  subsets: ["latin"],
  weight: ["400", "500", "700"],
  display: "swap",
  fallback: ["Arial", "sans-serif"],
});

export const metadata: Metadata = {
  title: "UNLPam | Gestión de Convenios",
  description: "Sistema de Gestión de Convenios de la UNLPam.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="es"
      data-theme="light"
      suppressHydrationWarning
      className={`${geistSans.variable} ${geistMono.variable} ${robotoCondensed.variable} h-full antialiased`}
    >
      <head>
        <script
          dangerouslySetInnerHTML={{
            __html: `(function(){try{var theme=localStorage.getItem("sgc_theme");document.documentElement.dataset.theme=theme==="dark"?"dark":"light"}catch(error){document.documentElement.dataset.theme="light";console.warn("No se pudo leer la preferencia del tema.",error)}})()`,
          }}
        />
      </head>
      <body className="min-h-full flex flex-col">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
