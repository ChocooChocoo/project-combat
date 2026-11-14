import { useEffect, useState } from 'react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { TextField } from '@/components/text-field'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { ATHLETE_LEVELS, type AthleteLevel, type SparringDetails } from '@/lib/membership'

type SparringMatch = {
  user_id: number
  name: string
  score: number
  reasons: string[]
  level: AthleteLevel | null
  record: string
}

const FIELDS = [
  { name: 'weight_kg', label: 'Weight (kg)', step: '0.1', min: 20, max: 200 },
  { name: 'height_cm', label: 'Height (cm)', step: '1', min: 90, max: 230 },
  { name: 'experience_years', label: 'Years of experience', step: '1', min: 0, max: 60 },
  { name: 'wins', label: 'Wins', step: '1', min: 0, max: 500 },
  { name: 'losses', label: 'Losses', step: '1', min: 0, max: 500 },
  { name: 'draws', label: 'Draws', step: '1', min: 0, max: 500 },
] as const

/** (R-140) An athlete's sparring details and suggested partners, with the reasons for each match. */
export function SparringCard({ gymId, athleteId, sparring, canEdit, canSee }: { gymId: number; athleteId: number; sparring: SparringDetails; canEdit: boolean; canSee: boolean }) {
  const [details, setDetails] = useState(sparring)
  const [matches, setMatches] = useState<SparringMatch[] | null>(null)
  const [notes, setNotes] = useState<string[]>([])
  const [error, setError] = useState('')
  const [open, setOpen] = useState(false)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    if (!canSee) return
    let live = true
    api<{ matches: SparringMatch[]; notes: string[] }>(`/gyms/${gymId}/athletes/${athleteId}/sparring-matches`)
      .then((result) => {
        if (!live) return
        setMatches(result.matches)
        setNotes(result.notes)
      })
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Sparring matches could not be loaded.'))
    return () => { live = false }
  }, [gymId, athleteId, canSee, version])

  async function save(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy(true)
    try {
      const body = Object.fromEntries(FIELDS.map(({ name }) => [name, values[name] === '' ? null : values[name]]))
      const result = await api<{ sparring: SparringDetails }>(`/gyms/${gymId}/athletes/${athleteId}/sparring`, { method: 'PUT', body })
      setDetails(result.sparring)
      setOpen(false)
      setErrors({})
      setVersion((current) => current + 1)
      toastManager.add({ title: 'Sparring details saved.', type: 'success' })
    } catch (failure) {
      const fieldErrors = failure instanceof ApiError ? failure.errors : {}
      setErrors(fieldErrors)
      if (Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not save.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  const shown = (value: number | null, unit = '') => (value === null ? 'Not recorded' : `${value}${unit}`)

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Sparring</CardTitle>
        <CardDescription>Weight, height, record and experience used to suggest sparring partners.</CardDescription>
        {canEdit && (
          <CardAction>
            <Button size="sm" variant="outline" onClick={() => setOpen(true)}>Edit</Button>
          </CardAction>
        )}
      </CardHeader>
      <CardPanel className="flex flex-col gap-4">
        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <div><dt className="text-muted-foreground text-xs">Weight</dt><dd>{shown(details.weight_kg, ' kg')}</dd></div>
          <div><dt className="text-muted-foreground text-xs">Height</dt><dd>{shown(details.height_cm, ' cm')}</dd></div>
          <div><dt className="text-muted-foreground text-xs">Experience</dt><dd>{shown(details.experience_years, details.experience_years === 1 ? ' year' : ' years')}</dd></div>
          <div><dt className="text-muted-foreground text-xs">Wins</dt><dd>{details.wins}</dd></div>
          <div><dt className="text-muted-foreground text-xs">Losses</dt><dd>{details.losses}</dd></div>
          <div><dt className="text-muted-foreground text-xs">Draws</dt><dd>{details.draws}</dd></div>
        </dl>

        {canSee && (
          <section className="flex flex-col gap-3" aria-label="Suggested sparring partners">
            <h3 className="font-medium text-sm">Suggested partners</h3>
            {error && <p className="text-destructive text-sm">{error}</p>}
            {notes.map((note) => (
              <Alert key={note} variant="info"><AlertDescription>{note}</AlertDescription></Alert>
            ))}
            {matches && matches.length === 0 && <p className="text-muted-foreground text-sm">No active members to match yet.</p>}
            <ul className="flex flex-col gap-2">
              {matches?.map((match) => (
                <li key={match.user_id} className="rounded-xl border p-3 text-sm">
                  <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <span className="break-words font-medium">{match.name}</span>
                    <span className="font-semibold">{match.score}/100</span>
                  </div>
                  <p className="text-muted-foreground text-xs">
                    {match.level ? ATHLETE_LEVELS[match.level] : 'Beginner'} · record {match.record}
                  </p>
                  <p className="mt-1 text-muted-foreground text-xs">{match.reasons.join(' · ')}</p>
                </li>
              ))}
            </ul>
          </section>
        )}
      </CardPanel>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogPopup>
          <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
            <DialogHeader>
              <DialogTitle>Sparring details</DialogTitle>
              <DialogDescription>Leave a box empty when you do not know the value.</DialogDescription>
            </DialogHeader>
            <DialogPanel className="grid gap-4 sm:grid-cols-2">
              {FIELDS.map((field) => (
                <TextField key={field.name} name={field.name} label={field.label} type="number" inputMode="decimal" step={field.step} min={field.min} max={field.max} defaultValue={details[field.name] ?? ''} />
              ))}
            </DialogPanel>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" loading={busy}>Save</Button>
            </DialogFooter>
          </Form>
        </DialogPopup>
      </Dialog>
    </Card>
  )
}
