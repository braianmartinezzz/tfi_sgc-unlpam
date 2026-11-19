import type { Paginated } from "@/types/api";

export const PAGE_SIZE = 10;

export function paginateItems<T>(items: readonly T[], requestedPage: number) {
  const lastPage = Math.max(Math.ceil(items.length / PAGE_SIZE), 1);
  const currentPage = Math.min(Math.max(requestedPage, 1), lastPage);

  return {
    currentPage,
    lastPage,
    items: items.slice(
      (currentPage - 1) * PAGE_SIZE,
      currentPage * PAGE_SIZE,
    ),
  };
}

export async function fetchAllPages<T>(
  fetchPage: (page: number) => Promise<Paginated<T>>,
): Promise<T[]> {
  const firstPage = await fetchPage(1);
  const items = [...firstPage.data];

  for (let page = 2; page <= firstPage.last_page; page += 1) {
    const response = await fetchPage(page);
    items.push(...response.data);
  }

  return items;
}
