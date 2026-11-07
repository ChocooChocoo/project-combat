import { Building2, Dumbbell } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Field, FieldDescription, FieldError } from '@/components/ui/field'
import { Radio, RadioGroup } from '@/components/ui/radio-group'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { passwordRule, sameAs } from '@/lib/validation'

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
    <AuthCard wide title="Create your account" description="Choose how you will use Combat Platform.">
      <Form className="flex flex-col gap-4" errors={errors} onSubmit={submit}>
        <Field name="account_type" className="mb-2 w-full">
          <span id="account-type-label" className="font-medium text-sm">Account type</span>
          <RadioGroup name="account_type" defaultValue={params.get('type') === 'owner' ? 'owner' : 'member'} required aria-labelledby="account-type-label" className="grid w-full gap-3 sm:grid-cols-2">
            {[
              { value: 'member', title: 'Gym Member', icon: Dumbbell, points: ['Browse approved gyms', 'Join gyms and book sessions', 'Find your best matches'] },
              { value: 'owner', title: 'Gym Owner', icon: Building2, points: ['Apply to run a gym', 'Run it once approved', 'Browse other gyms only'] },
            ].map(({ value, title, icon: Icon, points }) => (
              <label key={value} className="flex cursor-pointer flex-col gap-3 rounded-xl border p-4 hover:bg-accent/40 has-data-checked:border-primary has-data-checked:bg-accent/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-offset-2">
                <span className="flex items-center gap-2">
                  <Icon className="size-5 shrink-0" aria-hidden />
                  <span className="flex-1 font-semibold text-sm">{title}</span>
                  <Radio value={value} aria-label={title} />
                </span>
                <span role="list" className="flex flex-col gap-1 text-muted-foreground text-sm leading-relaxed">
                  {points.map((point) => <span role="listitem" key={point}>{point}</span>)}
                </span>
              </label>
            ))}
          </RadioGroup>
          <FieldDescription className="text-sm leading-relaxed">You can't switch later. Use another email for the other type.</FieldDescription>
          <FieldError />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <TextField name="first_name" label="First name" maxLength={100} autoComplete="given-name" required />
          <TextField name="last_name" label="Last name" maxLength={100} autoComplete="family-name" required />
        </div>
        <TextField name="email" label="Email" type="email" maxLength={255} autoComplete="email" required />
        <TextField
          name="password"
          label="Password"
          type="password"
          autoComplete="new-password"
          validate={passwordRule}
          description="At least 8 characters, with upper and lower case letters and a number."
          required
        />
        <TextField name="password_confirmation" label="Confirm password" type="password" autoComplete="new-password" validate={sameAs('password')} required />
        <Button type="submit" className="min-h-11" loading={busy}>Create account</Button>
        <p className="text-center text-muted-foreground text-sm">
          Already have an account? <Link className="inline-flex min-h-11 items-center rounded-sm text-foreground underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring" to="/sign-in">Sign in</Link>
        </p>
      </Form>
    </AuthCard>
  )
}
