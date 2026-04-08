import * as CheckboxPrimitive from "@radix-ui/react-checkbox"
import { CheckIcon, MinusIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

function Checkbox({
  className,
  ...props
}: React.ComponentProps<typeof CheckboxPrimitive.Root>) {
  return (
    <CheckboxPrimitive.Root
      data-slot="checkbox"
      className={cn(
        "peer size-4 shrink-0 !rounded-md border border-input/70 bg-muted/80 text-transparent shadow-[0_1px_2px_rgba(0,0,0,0.06)] transition-all outline-none data-[state=checked]:border-transparent data-[state=checked]:bg-[#d92400] data-[state=checked]:text-white data-[state=indeterminate]:border-transparent data-[state=indeterminate]:bg-[#d92400] data-[state=indeterminate]:text-white focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/40 dark:data-[state=checked]:bg-[#d92400] dark:data-[state=indeterminate]:bg-[#d92400] dark:aria-invalid:ring-destructive/40",
        className
      )}
      {...props}
    >
      <CheckboxPrimitive.Indicator
        data-slot="checkbox-indicator"
        className="flex items-center justify-center text-current transition-none"
      >
        {props.checked === 'indeterminate' ? (
          <MinusIcon className="size-3" strokeWidth={3} />
        ) : (
          <CheckIcon className="size-3" strokeWidth={3} />
        )}
      </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
  )
}

export { Checkbox }
