import { Building2, Check, Dumbbell } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Field, FieldDescription, FieldError } from '@/components/ui/field'
import { Fieldset, FieldsetLegend } from '@/components/ui/fieldset'
import { Label } from '@/components/ui/label'
import { Radio, RadioGroup } from '@/components/ui/radio-group'
import { Separator } from '@/components/ui/separator'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { passwordRule, sameAs } from '@/lib/validation'

const accountTypes = [
  { value: 'member', title: 'Gym Member', icon: Dumbbell, points: ['Browse approved gyms', 'Join gyms and book sessions', 'Find your best matches'] },
  { value: 'owner', title: 'Gym Owner', icon: Building2, points: ['Apply to run a gym', 'Run it once approved', 'Browse other gyms only'] },
]

/** S-05, R-01: one sign-up page for everyone. */
export default function SignUp() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy(true)
    try {
      await api('/auth/register', { body: values })
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
            <RadioGroup name="account_type" defaultValue={params.get('type') === 'owner' ? 'owner' : 'member'} required aria-labelledby="account-type-label" className="grid w-full gap-3 sm:grid-cols-2">
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
            <TextField name="last_name" label="Last name" size="lg" maxLength={100} autoComplete="family-name" required />
          </div>
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

        <Button type="submit" size="lg" className="w-full" loading={busy}>Create account</Button>
      </Form>
    </AuthCard>
  )
}
