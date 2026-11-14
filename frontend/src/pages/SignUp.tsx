import { Building2, Check, Dumbbell, MapPin } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Form } from '@/components/ui/form'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Fieldset, FieldsetLegend } from '@/components/ui/fieldset'
import { Label } from '@/components/ui/label'
import { Radio, RadioGroup } from '@/components/ui/radio-group'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { currentPosition } from '@/lib/geolocation'
import { ADULT_AGE, ageOn, MIN_AGE, passwordRule, phoneRule, sameAs, SUFFIXES } from '@/lib/validation'

const accountTypes = [
  { value: 'member', title: 'Gym Member', icon: Dumbbell, points: ['Browse approved gyms', 'Join gyms and book sessions', 'Find your best matches'] },
  { value: 'owner', title: 'Gym Owner', icon: Building2, points: ['Apply to run a gym', 'Run it once approved', 'Browse other gyms only'] },
]

const NO_SUFFIX = 'none'
const suffixItems = [{ value: NO_SUFFIX, label: 'None' }, ...SUFFIXES.map((s) => ({ value: s, label: s }))]
const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' })

/** S-05, R-01: one sign-up page for everyone. (R-128, R-129, R-130) */
export default function SignUp() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const [accountType, setAccountType] = useState(params.get('type') === 'owner' ? 'owner' : 'member')
  const [birthdate, setBirthdate] = useState('')
  const [suffix, setSuffix] = useState(NO_SUFFIX)
  const [consent, setConsent] = useState(false)
  const [city, setCity] = useState('')
  const [province, setProvince] = useState('')
  const [locating, setLocating] = useState(false)
  const [locationError, setLocationError] = useState('')

  const age = /^\d{4}-\d{2}-\d{2}$/.test(birthdate) ? ageOn(birthdate, today) : null
  const tooYoung = age !== null && age < MIN_AGE
  const minorOwner = age !== null && age >= MIN_AGE && age < ADULT_AGE && accountType === 'owner'
  const needsGuardian = age !== null && age >= MIN_AGE && age < ADULT_AGE && accountType === 'member'

  async function useMyLocation() {
    setLocating(true)
    setLocationError('')
    try {
      const { lat, lng } = await currentPosition()
      const { place } = await api<{ place: { city: string | null; province: string | null } }>(`/geo/reverse?lat=${lat}&lng=${lng}`)
      if (!city && place.city) setCity(place.city)
      if (!province && place.province) setProvince(place.province)
    } catch (error) {
      setLocationError(error instanceof Error ? error.message : 'Could not find your area.')
    } finally {
      setLocating(false)
    }
  }

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy(true)
    try {
      const body: Record<string, string> = { ...values, city, province }
      if (suffix !== NO_SUFFIX) body.suffix = suffix
      if (needsGuardian && consent) body.guardian_consent = '1'
      await api('/auth/register', { body })
      navigate(`/verify-email?email=${encodeURIComponent(values.email)}`)
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthCard
      wide
      title="Create your account"
      description="Choose how you will use CombatPro, then add your details."
      footer={<>Already have an account? <Button variant="link" size="sm" className="px-1" render={<Link to="/sign-in" />}>Sign in</Button></>}
    >
      <Form className="flex flex-col gap-6" errors={errors} onSubmit={submit}>
        <Field name="account_type" className="w-full">
          <Fieldset className="flex w-full flex-col gap-3">
            <FieldsetLegend id="account-type-label" className="text-sm">Account type</FieldsetLegend>
            <RadioGroup name="account_type" value={accountType} onValueChange={(value) => setAccountType(String(value))} required aria-labelledby="account-type-label" className="grid w-full gap-3 sm:grid-cols-2">
              {accountTypes.map(({ value, title, icon: Icon, points }) => (
                <Label key={value} className="flex cursor-pointer flex-col items-stretch gap-3 rounded-xl border p-4 hover:bg-accent/50 has-data-checked:border-primary/48 has-data-checked:bg-accent/50">
                  <span className="flex items-center gap-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-background">
                      <Icon className="size-4" aria-hidden />
                    </span>
                    <span className="flex-1 font-semibold">{title}</span>
                    <Radio value={value} aria-label={title} />
                  </span>
                  <span role="list" className="flex flex-col gap-1.5 font-normal text-muted-foreground">
                    {points.map((point) => (
                      <span role="listitem" key={point} className="flex items-center gap-2">
                        <Check className="size-3.5 shrink-0" aria-hidden />{point}
                      </span>
                    ))}
                  </span>
                </Label>
              ))}
            </RadioGroup>
            <FieldDescription>You can't switch later. Use another email for the other type.</FieldDescription>
            <FieldError />
          </Fieldset>
        </Field>

        <Separator />

        <Fieldset className="flex flex-col gap-4">
          <FieldsetLegend className="text-sm">Your details</FieldsetLegend>
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField name="first_name" label="First name" size="lg" maxLength={100} autoComplete="given-name" required />
            <TextField name="middle_name" label="Middle name (optional)" size="lg" maxLength={100} autoComplete="additional-name" />
            <TextField name="last_name" label="Last name" size="lg" maxLength={100} autoComplete="family-name" required />
            <Field name="suffix" className="w-full">
              <FieldLabel>Suffix</FieldLabel>
              <Select items={suffixItems} value={suffix} onValueChange={(value) => setSuffix(value ?? NO_SUFFIX)}>
                <SelectTrigger size="lg"><SelectValue /></SelectTrigger>
                <SelectPopup>
                  {suffixItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
                </SelectPopup>
              </Select>
              <FieldError />
            </Field>
          </div>
          <TextField name="birthdate" label="Birthdate" type="date" size="lg" max={today} autoComplete="bday" required value={birthdate} onChange={(e) => setBirthdate(e.target.value)} />
          {tooYoung && <p role="alert" className="text-destructive text-sm">Children under {MIN_AGE} cannot have an account.</p>}
          {minorOwner && <p role="alert" className="text-destructive text-sm">Gym owners must be {ADULT_AGE} or older.</p>}
          {needsGuardian && (
            <Fieldset className="flex flex-col gap-4 rounded-xl border p-4">
              <FieldsetLegend className="px-1 text-sm">Parent or guardian</FieldsetLegend>
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField name="guardian_name" label="Guardian's name" size="lg" maxLength={150} required />
                <TextField name="guardian_phone" label="Guardian's phone" type="tel" size="lg" maxLength={30} validate={phoneRule} required />
              </div>
              <TextField name="guardian_relationship" label="Relationship to you" size="lg" maxLength={60} placeholder="Mother, father, aunt..." required />
              <Field name="guardian_consent" className="w-full">
                <label className="flex items-start gap-3 text-sm">
                  <Checkbox className="mt-0.5" checked={consent} onCheckedChange={(checked) => setConsent(checked === true)} />
                  My parent or guardian agrees to this account and to my training.
                </label>
                <FieldError />
              </Field>
            </Fieldset>
          )}
          <Fieldset className="flex flex-col gap-3">
            <FieldsetLegend className="text-sm">Your area (optional)</FieldsetLegend>
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="city" label="City" size="lg" maxLength={120} autoComplete="address-level2" value={city} onChange={(e) => setCity(e.target.value)} />
              <TextField name="province" label="Province" size="lg" maxLength={120} autoComplete="address-level1" value={province} onChange={(e) => setProvince(e.target.value)} />
            </div>
            <div className="flex flex-col items-start gap-2">
              <Button type="button" variant="outline" loading={locating} onClick={useMyLocation}><MapPin aria-hidden />Use my location</Button>
              {locationError && <p role="alert" className="text-destructive text-sm">{locationError}</p>}
            </div>
          </Fieldset>
          <TextField name="email" label="Email" type="email" size="lg" placeholder="you@example.com" maxLength={255} autoComplete="email" required />
          <TextField
            name="password"
            label="Password"
            type="password"
            size="lg"
            autoComplete="new-password"
            validate={passwordRule}
            description="At least 8 characters, with upper and lower case letters and a number."
            required
          />
          <TextField name="password_confirmation" label="Confirm password" type="password" size="lg" autoComplete="new-password" validate={sameAs('password')} required />
        </Fieldset>

        <Button type="submit" size="lg" className="w-full" loading={busy} disabled={tooYoung || minorOwner}>Create account</Button>
      </Form>
    </AuthCard>
  )
}
