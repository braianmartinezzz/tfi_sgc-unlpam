import { forwardRef, useId, type InputHTMLAttributes } from "react";

type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & {
  label: string;
};

const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(function Checkbox(
  { className, id: providedId, label, ...props },
  ref,
) {
  const generatedId = useId();
  const id = providedId ?? generatedId;

  return (
    <div>
      <label htmlFor={id} className="inline-flex min-h-11 items-center gap-3">
        <input
          {...props}
          ref={ref}
          id={id}
          type="checkbox"
          className={`h-4 w-4 rounded-sm border-ui-border accent-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 ${className ?? ""}`}
        />
        <span className="text-sm font-medium">{label}</span>
      </label>
    </div>
  );
});

export default Checkbox;
