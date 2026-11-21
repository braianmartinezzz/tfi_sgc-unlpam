import { forwardRef, useId, type SelectHTMLAttributes } from "react";

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label: string;
  requiredMark?: boolean;
  floatingLabel?: boolean;
};

const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  {
    className,
    floatingLabel = false,
    id: providedId,
    label,
    requiredMark = false,
    ...props
  },
  ref,
) {
  const generatedId = useId();
  const id = providedId ?? generatedId;

  return (
    <div className={floatingLabel ? "relative" : undefined}>
      <label
        htmlFor={id}
        className={
          floatingLabel
            ? "absolute -top-2 left-3 z-10 bg-surface px-1 text-xs font-medium text-foreground"
            : "mb-1 block text-sm font-medium"
        }
      >
        {label}
        {requiredMark ? <span aria-hidden="true"> *</span> : null}
      </label>
      <select
        {...props}
        ref={ref}
        id={id}
        className={`h-11 w-full rounded-sm border border-ui-border bg-surface px-3 text-sm text-foreground outline-none hover:border-foreground focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/30 disabled:cursor-not-allowed disabled:opacity-70 ${className ?? ""}`}
      />
    </div>
  );
});

export default Select;
