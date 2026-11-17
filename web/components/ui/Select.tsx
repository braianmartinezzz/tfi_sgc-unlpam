import { forwardRef, useId, type SelectHTMLAttributes } from "react";

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label: string;
  requiredMark?: boolean;
};

const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  { className, id: providedId, label, requiredMark = false, ...props },
  ref,
) {
  const generatedId = useId();
  const id = providedId ?? generatedId;

  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium">
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
