import { forwardRef, useId, type TextareaHTMLAttributes } from "react";

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
  label: string;
};

const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
  { className, id: providedId, label, ...props },
  ref,
) {
  const generatedId = useId();
  const id = providedId ?? generatedId;

  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium">
        {label}
      </label>
      <textarea
        {...props}
        ref={ref}
        id={id}
        className={`min-h-28 w-full rounded-sm border border-ui-border bg-surface px-3 py-2 text-sm text-foreground outline-none placeholder:text-foreground/60 hover:border-foreground focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/30 ${className ?? ""}`}
      />
    </div>
  );
});

export default Textarea;
