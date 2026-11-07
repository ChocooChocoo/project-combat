import { TextField } from '@/components/text-field'
import { validRange, type Range } from '@/lib/decision-support'

export function RangePicker({ value, onChange }: { value: Range; onChange: (range: Range) => void }) {
  const invalid = !validRange(value.from, value.to)
  return <div className="ds-range print:hidden"><div className="grid grid-cols-2 gap-3">
    <TextField name="decision-from" label="From" type="date" value={value.from} aria-invalid={invalid} onChange={(event) => {
      const from = event.target.value
      onChange({ from, to: from && value.to && from > value.to ? from : value.to })
    }} />
    <TextField name="decision-to" label="To" type="date" value={value.to} aria-invalid={invalid} onChange={(event) => {
      const to = event.target.value
      onChange({ from: to && value.from && to < value.from ? to : value.from, to })
    }} />
  </div>{invalid && <p role="alert" className="mt-2 text-sm text-destructive">Enter valid dates with the end on or after the start.</p>}</div>
}
