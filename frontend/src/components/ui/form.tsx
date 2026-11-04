"use client";

import { Form as FormPrimitive } from "@base-ui/react/form";
import type React from "react";

export function Form({
  className,
  validationMode = "onBlur",
  ...props
}: FormPrimitive.Props): React.ReactElement {
  return (
    <FormPrimitive
      className={className}
      data-slot="form"
      validationMode={validationMode}
      {...props}
    />
  );
}

export { FormPrimitive };
