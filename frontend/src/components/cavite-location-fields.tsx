import { useEffect, useState } from 'react'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { api } from '@/lib/api'

type Municipality = { name: string; zip: string; barangays: string[] }

// One request per page load; the list never changes while the app runs.
let cache: Promise<Municipality[]> | null = null
export const loadMunicipalities = () =>
  (cache ??= api<{ municipalities: Municipality[] }>('/locations/cavite')
    .then((r) => r.municipalities)
    .catch((error) => {
      cache = null
      throw error
    }))

type Props = {
  city: string
  barangay: string
  disabled?: boolean
  /** Picking a city clears the barangay, because barangays belong to one city. */
  onChange: (value: { city: string; barangay: string }) => void
}

/** Province is fixed to Cavite. Barangays depend on the city; the zip code follows it. */
export function CaviteLocationFields({ city, barangay, disabled, onChange }: Props) {
  const [municipalities, setMunicipalities] = useState<Municipality[]>([])
  useEffect(() => {
    loadMunicipalities().then(setMunicipalities).catch(() => {})
  }, [])

  const current = municipalities.find((m) => m.name === city)
  const cityItems = municipalities.map((m) => ({ value: m.name, label: m.name }))
  const barangayItems = (current?.barangays ?? []).map((b) => ({ value: b, label: b }))

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Field name="province">
        <FieldLabel>Province</FieldLabel>
        <Input value="Cavite" readOnly disabled />
      </Field>
      <Field name="city">
        <FieldLabel>City or municipality</FieldLabel>
        <Select
          items={cityItems}
          value={city || null}
          disabled={disabled}
          onValueChange={(value) => onChange({ city: value ?? '', barangay: '' })}
        >
          <SelectTrigger><SelectValue>{(value: string | null) => value ?? <span className="text-muted-foreground">Select</span>}</SelectValue></SelectTrigger>
          <SelectPopup>
            {cityItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
          </SelectPopup>
        </Select>
        <FieldError />
      </Field>
      <Field name="barangay">
        <FieldLabel>Barangay</FieldLabel>
        <Select
          items={barangayItems}
          value={barangay || null}
          disabled={disabled || !current}
          onValueChange={(value) => onChange({ city, barangay: value ?? '' })}
        >
          <SelectTrigger><SelectValue>{(value: string | null) => value ?? <span className="text-muted-foreground">{current ? 'Select' : 'Pick a city first'}</span>}</SelectValue></SelectTrigger>
          <SelectPopup>
            {barangayItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
          </SelectPopup>
        </Select>
        <FieldError />
      </Field>
      <Field name="zip_code">
        <FieldLabel>Zip code</FieldLabel>
        <Input value={current?.zip ?? ''} placeholder="Filled in automatically" readOnly disabled />
      </Field>
    </div>
  )
}
