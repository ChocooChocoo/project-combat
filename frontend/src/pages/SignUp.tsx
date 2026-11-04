import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { passwordRule, sameAs } from '@/lib/validation'

/** S-05, R-01: one sign-up page for everyone. */
export default function SignUp() {
  const navigate = useNavigate()
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
    <AuthCard title="Create your account" description="One account for finding gyms, joining one, or running your own.">
      <Form className="flex flex-col gap-4" errors={errors} onSubmit={submit}>
        <div className="grid grid-cols-2 gap-3">
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
        <Button type="submit" loading={busy}>Create account</Button>
        <p className="text-center text-muted-foreground text-sm">
          Already have an account? <Link className="text-foreground underline" to="/sign-in">Sign in</Link>
        </p>
      </Form>
    </AuthCard>
  )
}
