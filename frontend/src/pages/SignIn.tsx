import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { useAuth, type User } from '@/lib/auth'

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
      navigate('/', { replace: true })
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
    <AuthCard title="Sign in">
      <Form className="flex flex-col gap-4" errors={errors} onSubmit={submit}>
        {message && <Alert variant="warning"><AlertDescription>{message}</AlertDescription></Alert>}
        <TextField name="email" label="Email" type="email" autoComplete="email" required />
        <TextField name="password" label="Password" type="password" autoComplete="current-password" required />
        <Button type="submit" loading={busy}>Sign in</Button>
        <div className="flex justify-between text-sm">
          <Link className="underline" to="/forgot-password">Forgot password?</Link>
          <Link className="underline" to="/sign-up">Create account</Link>
        </div>
      </Form>
    </AuthCard>
  )
}
