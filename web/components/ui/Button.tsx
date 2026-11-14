import { forwardRef, type ButtonHTMLAttributes } from "react";

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  loading?: boolean;
  variant?: "primary" | "link";
};

const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  {
    children,
    className,
    disabled,
    loading = false,
    type = "button",
    variant = "primary",
    ...props
  },
  ref,
) {
  const variantClassName =
    variant === "link"
      ? "h-auto w-auto rounded-none bg-transparent p-0 text-brand underline decoration-1 underline-offset-2 hover:text-brand-hover"
      : "w-full rounded-sm bg-brand px-4 py-2.5 text-base font-medium text-white shadow-sm hover:bg-brand-hover disabled:bg-brand/60";

  return (
    <button
      {...props}
      ref={ref}
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={`inline-flex items-center justify-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70 ${variantClassName} ${className ?? ""}`}
    >
      {loading ? (
        <svg
          aria-hidden="true"
          className="mr-2 h-4 w-4 animate-spin"
          viewBox="0 0 24 24"
          fill="none"
        >
          <circle
            className="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            strokeWidth="4"
          />
          <path
            className="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"
          />
        </svg>
      ) : null}
      {children}
    </button>
  );
});

export default Button;
