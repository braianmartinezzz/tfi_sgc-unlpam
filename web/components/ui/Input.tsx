import { forwardRef, useId, type InputHTMLAttributes, type ReactNode } from "react";

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  icon: ReactNode;
  endAdornment?: ReactNode;
  floatingLabel?: boolean;
};

const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  {
    className,
    endAdornment,
    floatingLabel = true,
    icon,
    id: providedId,
    label,
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
            ? "absolute -top-2 left-9 z-10 bg-surface px-1 text-xs font-medium text-foreground"
            : "mb-1 block text-sm font-medium"
        }
      >
        {label}
      </label>
      <div className="relative">
        <span
          aria-hidden="true"
          className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-foreground"
        >
          {icon}
        </span>
        <input
          {...props}
          ref={ref}
          id={id}
          className={`h-11 w-full rounded-sm border border-ui-border bg-surface py-2 pl-10 pr-3 text-sm text-foreground outline-none placeholder:text-foreground/60 hover:border-foreground focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/30 ${endAdornment ? "pr-32" : ""} ${className ?? ""}`}
        />
        {endAdornment ? (
          <span className="absolute inset-y-0 right-2 flex items-center">
            {endAdornment}
          </span>
        ) : null}
      </div>
    </div>
  );
});

export default Input;
