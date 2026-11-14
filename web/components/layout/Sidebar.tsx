import Link from "next/link";

const navigation = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/convenios", label: "Convenios" },
  { href: "/vencimientos", label: "Vencimientos" },
  { href: "/unidades", label: "Unidades académicas" },
  { href: "/usuarios", label: "Usuarios" },
  { href: "/revision", label: "Revisión" },
  { href: "/alertas", label: "Alertas" },
  { href: "/auditoria", label: "Auditoría" },
  { href: "/configuracion", label: "Configuración" },
];

export default function Sidebar() {
  return (
    <aside className="w-full border-b border-black/10 p-4 dark:border-white/10 md:min-h-screen md:w-60 md:border-b-0 md:border-r">
      <nav aria-label="Navegación principal">
        <ul className="flex flex-wrap gap-3 md:flex-col">
          {navigation.map((item) => (
            <li key={item.href}>
              <Link href={item.href}>{item.label}</Link>
            </li>
          ))}
        </ul>
      </nav>
    </aside>
  );
}
