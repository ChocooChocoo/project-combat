import { FileText } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { fileSizeText } from '@/lib/discovery'

type Kiosk = {
  gym: { id: number; name: string; policies: string | null; policy_files: { id: number; name: string; size: number; url: string }[]; needs_terms: boolean }
  health_questions: Record<string, string>
  waivers: Record<string, string>
}

/** R-137: the public walk-in kiosk. No sign-in; the visitor leaves details and pays at the desk. */
export default function WalkIn() {
  const { gymId } = useParams()
  const [kiosk, setKiosk] = useState<Kiosk | null>(null)
  const [loadError, setLoadError] = useState('')
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [answers, setAnswers] = useState<Record<string, boolean>>({})
  const [notes, setNotes] = useState('')
  const [waivers, setWaivers] = useState<Record<string, boolean>>({})
  const [signed, setSigned] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState('')
  const [done, setDone] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    api<Kiosk>(`/public/gyms/${gymId}/walk-in`)
      .then((result) => live && setKiosk(result))
      .catch((error) => live && setLoadError(error instanceof ApiError && error.status === 404 ? 'This gym is not taking walk-ins.' : 'The walk-in form could not be loaded.'))
    return () => { live = false }
  }, [gymId])

  const reset = () => {
    setName(''); setPhone(''); setEmail(''); setAnswers({}); setNotes(''); setWaivers({}); setSigned(''); setErrors({}); setMessage(''); setDone('')
  }

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setBusy(true)
    setErrors({})
    setMessage('')
    try {
      await api(`/public/gyms/${gymId}/walk-ins`, {
        body: {
          name: name.trim(), phone: phone.trim(), email: email.trim() || null,
          health_answers: answers, health_notes: notes.trim() || null, waivers, signed_name: signed.trim(),
        },
      })
      setDone(name.trim())
    } catch (error) {
      if (error instanceof ApiError && error.status === 429) setMessage('Too many sign-ins from this device. Wait a minute and try again.')
      else if (error instanceof ApiError) { setErrors(error.errors); setMessage(error.message) } else setMessage('Could not send your details. Try again.')
    } finally {
      setBusy(false)
    }
  }

  if (loadError) return <AuthCard title="Walk-in"><Alert variant="error"><AlertDescription>{loadError}</AlertDescription></Alert></AuthCard>
  if (!kiosk) return <AuthCard title="Walk-in"><p role="status" className="text-muted-foreground text-sm">Loading…</p></AuthCard>
  const { gym } = kiosk
  if (done) {
    return <AuthCard title={`Walk-in at ${gym.name}`} wide>
      <div className="flex flex-col gap-4">
        <p role="status" className="text-lg">Thank you, {done}. Please pay at the front desk.</p>
        <Button className="self-start" onClick={reset}>Next visitor</Button>
      </div>
    </AuthCard>
  }

  return <AuthCard title={`Walk-in at ${gym.name}`} description="Tell us who you are, then pay at the front desk." wide>
    <Form errors={errors} onSubmit={submit} className="flex flex-col gap-5">
      {message && <Alert variant="error"><AlertDescription>{message}</AlertDescription></Alert>}
      <TextField name="name" label="Name" value={name} onChange={(e) => setName(e.target.value)} maxLength={150} autoComplete="name" required />
      <TextField name="phone" label="Phone" type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} maxLength={30} autoComplete="tel" required />
      <TextField name="email" label="Email (optional)" type="email" value={email} onChange={(e) => setEmail(e.target.value)} maxLength={255} autoComplete="email" />
      <fieldset className="flex flex-col gap-2">
        <legend className="font-medium text-sm">Health (optional)</legend>
        {Object.entries(kiosk.health_questions).map(([key, question]) => (
          <label key={key} className="flex items-start gap-3 text-sm">
            <Checkbox className="mt-0.5" checked={answers[key] ?? false} onCheckedChange={(checked) => setAnswers((c) => ({ ...c, [key]: checked }))} />
            <span>{question} <span className="text-muted-foreground">Yes</span></span>
          </label>
        ))}
        <Field name="health_notes" className="w-full">
          <FieldLabel>Notes</FieldLabel>
          <Textarea name="health_notes" value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} />
          <FieldError />
        </Field>
      </fieldset>
      <div className="flex flex-col gap-3">
        {gym.policies && <p className="whitespace-pre-line rounded-lg border bg-muted/40 px-3 py-2.5 text-muted-foreground text-sm">{gym.policies}</p>}
        {gym.policy_files.length > 0 && <ul className="flex flex-col gap-1 text-sm">
          {gym.policy_files.map((file) => <li key={file.id}>
            <a className="inline-flex items-center gap-2 underline-offset-4 hover:underline" href={file.url} target="_blank" rel="noreferrer">
              <FileText className="size-4 shrink-0 text-muted-foreground" aria-hidden />{file.name} <span className="text-muted-foreground">({fileSizeText(file.size)})</span>
            </a>
          </li>)}
        </ul>}
        {[...(gym.needs_terms ? [['gym_terms', `I have read ${gym.name}'s policies and waiver and accept them.`]] : []), ...Object.entries(kiosk.waivers)].map(([key, text]) => (
          <Field key={key} name={`waivers.${key}`} className="w-full">
            <label className="flex items-start gap-3 text-sm">
              <Checkbox className="mt-0.5" checked={waivers[key] ?? false} onCheckedChange={(checked) => setWaivers((c) => ({ ...c, [key]: checked }))} />
              {text}
            </label>
            <FieldError />
          </Field>
        ))}
      </div>
      <TextField name="signed_name" label="Type your full name to sign" value={signed} onChange={(e) => setSigned(e.target.value)} maxLength={150} required />
      <Button type="submit" loading={busy} disabled={busy}>Send</Button>
    </Form>
  </AuthCard>
}
