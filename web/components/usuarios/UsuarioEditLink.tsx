"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import type { MouseEvent, ReactNode } from "react";

export default function UsuarioEditLink({
  id,
  className,
  children,
}: {
  id: number | string;
  className: string;
  children: ReactNode;
}) {
  const router = useRouter();
  const href = `/usuarios/${id}/edit`;

  function handleClick(event: MouseEvent<HTMLAnchorElement>) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return;
    }

    event.preventDefault();
    const visit = window.crypto.randomUUID();
    router.push(`${href}?visit=${visit}`);
  }

  return (
    <Link href={href} onClick={handleClick} className={className}>
      {children}
    </Link>
  );
}
