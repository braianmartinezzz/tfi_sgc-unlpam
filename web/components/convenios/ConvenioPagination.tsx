"use client";

interface ConvenioPaginationProps {
  currentPage: number;
  lastPage: number;
  total: number;
  onPageChange: (page: number) => void;
  ariaLabel?: string;
}

export default function ConvenioPagination({
  currentPage,
  lastPage,
  total,
  onPageChange,
  ariaLabel = "Paginación de convenios",
}: ConvenioPaginationProps) {
  const firstPage = Math.max(1, currentPage - 2);
  const finalPage = Math.min(lastPage, currentPage + 2);
  const pages = Array.from(
    { length: finalPage - firstPage + 1 },
    (_, index) => firstPage + index,
  );

  return (
    <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm">
        Total de resultados: <span className="font-semibold">{total}</span>
      </p>
      <nav
        aria-label={ariaLabel}
        className="flex flex-wrap items-center justify-start gap-1 sm:justify-end"
      >
        <button
          type="button"
          disabled={currentPage <= 1}
          onClick={() => onPageChange(currentPage - 1)}
          className="min-h-10 enabled:cursor-pointer rounded-md border border-ui-border px-3 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-50"
        >
          Anterior
        </button>
        {firstPage > 1 ? (
          <>
            <PageButton page={1} currentPage={currentPage} onPageChange={onPageChange} />
            {firstPage > 2 ? <span className="px-1" aria-hidden="true">…</span> : null}
          </>
        ) : null}
        {pages.map((page) => (
          <PageButton
            key={page}
            page={page}
            currentPage={currentPage}
            onPageChange={onPageChange}
          />
        ))}
        {finalPage < lastPage ? (
          <>
            {finalPage < lastPage - 1 ? (
              <span className="px-1" aria-hidden="true">
                …
              </span>
            ) : null}
            <PageButton
              page={lastPage}
              currentPage={currentPage}
              onPageChange={onPageChange}
            />
          </>
        ) : null}
        <button
          type="button"
          disabled={currentPage >= lastPage}
          onClick={() => onPageChange(currentPage + 1)}
          className="min-h-10 enabled:cursor-pointer rounded-md border border-ui-border px-3 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-50"
        >
          Siguiente
        </button>
        <button
          type="button"
          disabled={currentPage >= lastPage}
          onClick={() => onPageChange(lastPage)}
          className="min-h-10 enabled:cursor-pointer rounded-md border border-ui-border px-3 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-50"
        >
          Última
        </button>
      </nav>
    </div>
  );
}

function PageButton({
  page,
  currentPage,
  onPageChange,
}: {
  page: number;
  currentPage: number;
  onPageChange: (page: number) => void;
}) {
  const isCurrent = page === currentPage;

  return (
    <button
      type="button"
      aria-label={`Página ${page}`}
      aria-current={isCurrent ? "page" : undefined}
      onClick={() => onPageChange(page)}
      className={`min-h-10 min-w-10 cursor-pointer rounded-md border px-3 py-2 font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand ${
        isCurrent
          ? "border-brand bg-brand text-white"
          : "border-ui-border hover:bg-row-alternate"
      }`}
    >
      {page}
    </button>
  );
}
