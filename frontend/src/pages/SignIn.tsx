import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { useAuth, type User } from '@/lib/auth'
import { homePath } from '@/lib/gym-access'

/** S-07, R-03. */
export default function SignIn() {
  const navigate = useNavigate()
  const { signIn } = useAuth()
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy(true)
    setMessage('')
    try {
      const result = await api<{ token: string; user: User }>('/auth/login', { body: values })
      signIn(result.token, result.user)
      navigate(homePath(result.user), { replace: true })
    } catch (error) {
      if (!(error instanceof ApiError)) return
      if (error.data.needs_verification) {
        await api('/auth/resend-code', { body: { email: values.email } })
        navigate(`/verify-email?email=${encodeURIComponent(values.email)}`)
        return
      }
      setErrors(error.errors)
      if (error.status === 429) setMessage(error.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthCard title="Sign in" description="Access your gyms, sessions and account.">
      <Form className="flex flex-col gap-4" errors={errors} onSubmit={submit}>
        {message && <Alert variant="warning"><AlertDescription>{message}</AlertDescription></Alert>}
        <TextField name="email" label="Email" type="email" autoComplete="email" required />
        <Field name="password" className="w-full">
          <div className="flex w-full flex-wrap items-center justify-between gap-x-3">
            <FieldLabel>Password</FieldLabel>
            <FieldDescription>
              <Link className="inline-flex min-h-11 items-center rounded-sm text-foreground text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring" to="/forgot-password">Forgot password?</Link>
            </FieldDescription>
          </div>
          <Input name="password" type="password" autoComplete="current-password" required />
          <FieldError />
        </Field>
        <Button type="submit" className="min-h-11" loading={busy}>Sign in</Button>
        <p className="text-center text-muted-foreground text-sm">
          New here? <Link className="inline-flex min-h-11 items-center rounded-sm text-foreground underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring" to="/sign-up">Create an account</Link>
        </p>
      </Form>
    </AuthCard>
  )
}
