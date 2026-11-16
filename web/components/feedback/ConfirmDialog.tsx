"use client";

import { useEffect, useRef } from "react";

interface ConfirmDialogProps {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  loading: boolean;
  errorMessage: string | null;
  returnFocusRef: { current: HTMLButtonElement | null };
  onConfirm: () => void;
  onCancel: () => void;
}

export default function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  loading,
  errorMessage,
  returnFocusRef,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;

    if (open && !dialog.open) {
      dialog.showModal();
    } else if (!open && dialog.open) {
      dialog.close();
      returnFocusRef.current?.focus();
    }
  }, [open, returnFocusRef]);

  return (
    <dialog
      ref={dialogRef}
      aria-labelledby="confirm-dialog-title"
      aria-describedby="confirm-dialog-description"
      onCancel={(event) => {
        event.preventDefault();
        if (!loading) onCancel();
      }}
      className="m-auto w-[calc(100%-2rem)] max-w-lg rounded-lg border border-ui-border bg-surface p-0 text-foreground shadow-xl backdrop:bg-black/60"
    >
      <div className="space-y-4 p-5 sm:p-6">
        <h2 id="confirm-dialog-title" className="text-xl font-bold">
          {title}
        </h2>
        <p id="confirm-dialog-description">{description}</p>
        {errorMessage ? (
          <p
            role="alert"
            className="text-sm font-medium text-red-700 dark:text-red-300"
          >
            {errorMessage}
          </p>
        ) : null}
        <div className="flex flex-wrap justify-end gap-3 pt-2">
          <button
            type="button"
            disabled={loading}
            onClick={onCancel}
            className="rounded-md border border-ui-border px-4 py-2 font-semibold hover:bg-row-alternate focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
          >
            Cancelar
          </button>
          <button
            type="button"
            disabled={loading}
            aria-busy={loading}
            onClick={onConfirm}
            className="rounded-md bg-brand px-4 py-2 font-semibold text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {loading ? "Eliminando..." : confirmLabel}
          </button>
        </div>
      </div>
    </dialog>
  );
}
