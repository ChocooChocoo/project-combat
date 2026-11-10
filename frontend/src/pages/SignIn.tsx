import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
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
    <AuthCard
      title="Welcome back"
      description="Sign in to reach your gyms, sessions and account."
      footer={<>New here? <Button variant="link" size="sm" className="px-1" render={<Link to="/sign-up" />}>Create an account</Button></>}
    >
      <Form
        className="flex flex-col gap-5"
        errors={errors}
        onChange={() => {
          // The server error sits on email, so editing only the password must clear it too.
          // Base UI blocks the next submit while any field is still marked invalid.
          if (Object.keys(errors).length > 0) setErrors({})
        }}
        onSubmit={submit}
      >
        {message && <Alert variant="warning"><AlertDescription>{message}</AlertDescription></Alert>}
        <TextField name="email" label="Email" type="email" size="lg" placeholder="you@example.com" autoComplete="email" required />
        <Field name="password" className="w-full">
          <div className="flex w-full items-center justify-between gap-3">
            <FieldLabel>Password</FieldLabel>
            <Button variant="link" size="xs" className="-me-2 text-muted-foreground" render={<Link to="/forgot-password" />}>Forgot password?</Button>
          </div>
          <Input name="password" type="password" size="lg" autoComplete="current-password" required />
          <FieldError />
        </Field>
        <Button type="submit" size="lg" className="w-full" loading={busy}>Sign in</Button>
      </Form>
    </AuthCard>
  )
}
