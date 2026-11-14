import ThemeToggle from "@/components/layout/ThemeToggle";

export default function Header() {
  return (
    <header className="flex items-center justify-between border-b border-black/10 p-4 dark:border-white/10">
      <p className="font-semibold">Gestión de Convenios</p>
      <ThemeToggle />
    </header>
  );
}
