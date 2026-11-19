"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { usePermissions } from "@/context/PermissionContext";
import type { Permission } from "@/lib/permissions";

interface NavigationItem {
  href: string;
  label: string;
  icon: string;
  permission?: Permission;
}

const navigation: NavigationItem[] = [
  {
    href: "/convenios",
    label: "Convenios",
    icon: "M4 5h16v14H4z M8 9h8 M8 13h8 M8 17h4",
  },
  {
    href: "/usuarios",
    label: "Usuarios",
    icon: "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 8v6 M23 11h-6",
    permission: "usuarios:write",
  },
  {
    href: "/instituciones",
    label: "Instituciones firmantes",
    icon: "M4 20h16 M6 20V6h12v14 M9 9h6 M9 13h6 M9 17h6",
    permission: "catalogos:write",
  },
  {
    href: "/unidades",
    label: "Unidades académicas",
    icon: "M3 9 12 4l9 5-9 5-9-5 M5 11v6c4 3 10 3 14 0v-6 M21 9v7",
    permission: "catalogos:write",
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
  const { can } = usePermissions();
  const visibleNavigation = navigation.filter(
    (item) => !item.permission || can(item.permission),
  );

  return (
    <aside className="w-full shrink-0 bg-sidebar text-white md:h-full md:min-h-0 md:w-60">
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
          {visibleNavigation.map((item) => {
            const isActive =
              pathname === item.href ||
              pathname.startsWith(`${item.href}/`);

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
