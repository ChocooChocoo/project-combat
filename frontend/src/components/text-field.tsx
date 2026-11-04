import { Field, type FieldPrimitive, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'

type Props = React.ComponentProps<typeof Input> & {
  name: string
  label: string
  description?: string
  validate?: FieldPrimitive.Root.Props['validate']
}

/** A labelled input. Server errors for `name` show beneath it through the parent Form. */
export function TextField({ name, label, description, validate, ...input }: Props) {
  return (
    <Field name={name} className="w-full" validate={validate}>
      <FieldLabel>{label}</FieldLabel>
      <Input name={name} {...input} />
      {description && <FieldDescription>{description}</FieldDescription>}
      <FieldError />
    </Field>
  )
}
