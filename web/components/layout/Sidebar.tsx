"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";

const navigation = [
  {
    href: "/convenios",
    label: "Convenios",
    icon: "M4 5h16v14H4z M8 9h8 M8 13h8 M8 17h4",
  },
  {
    href: "/dashboard",
    label: "Estadísticas",
    icon: "M4 19V5 M4 19h16 M8 15v-4 M12 15V7 M16 15V9",
  },
];

export default function Sidebar() {
  const pathname = usePathname();
  const [isOpen, setIsOpen] = useState(false);

  return (
    <aside className="w-full shrink-0 bg-sidebar text-white md:min-h-full md:w-60">
      <button
        type="button"
        className="flex w-full items-center justify-between px-4 py-3 text-left font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white md:hidden"
        aria-expanded={isOpen}
        aria-controls="primary-navigation"
        onClick={() => setIsOpen((open) => !open)}
      >
        <span>Menú</span>
        <svg
          aria-hidden="true"
          className={`h-5 w-5 transition-transform ${isOpen ? "rotate-180" : ""}`}
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
        >
          <path d="m6 9 6 6 6-6" />
        </svg>
      </button>
      <nav
        id="primary-navigation"
        aria-label="Navegación principal"
        className={`${isOpen ? "block" : "hidden"} md:block`}
      >
        <ul className="flex flex-col gap-1 pb-3 md:py-4">
          {navigation.map((item) => {
            const isActive =
              pathname === item.href ||
              (item.href === "/convenios" &&
                pathname.startsWith("/convenios/"));

            return (
              <li key={item.href}>
                <Link
                  href={item.href}
                  aria-current={isActive ? "page" : undefined}
                  onClick={() => setIsOpen(false)}
                  className={`flex items-center gap-3 border-l-4 px-4 py-3 text-base font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white ${
                    isActive
                      ? "border-brand bg-brand text-white"
                      : "border-transparent text-white hover:bg-white/10"
                  }`}
                >
                  <svg
                    aria-hidden="true"
                    className="h-5 w-5 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    <path d={item.icon} />
                  </svg>
                  <span>{item.label}</span>
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>
    </aside>
  );
}
