"use client";

import { useQuery } from "@tanstack/react-query";
import * as roles from "@/services/roles";

export function useRoles() {
  return useQuery({
    queryKey: ["roles"],
    queryFn: () => roles.list(),
  });
}
