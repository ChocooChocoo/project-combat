import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'

/** S-06 follow-on: an invited employee sets their own password with the emailed code. */
export default function AcceptInvite() {
  const [params] = useSearchParams()
  const email = params.get('email') ?? ''
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState('')
  const [done, setDone] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy(true)
    setErrors({})
    setMessage('')
    try {
      const result = await api<{ message: string }>('/auth/accept-invite', { body: values })
      setDone(result.message)
    } catch (error) {
      if (!(error instanceof ApiError)) return
      setErrors(error.errors)
      if (error.status === 429 || Object.keys(error.errors).length === 0) setMessage(error.message)
    } finally {
      setBusy(false)
    }
  }

  if (done) {
    return (
      <AuthCard title="Account ready" description="Your password is set.">
        <div className="flex flex-col gap-4">
          <Alert variant="success">
            <AlertDescription>{done}</AlertDescription>
          </Alert>
          <Button render={<Link to="/sign-in" />}>Go to sign in</Button>
        </div>
      </AuthCard>
    )
  }

  return (
    <AuthCard
      title="Set up your account"
      description="Use the 6-digit code from your gym's invitation and choose your password."
    >
      <Form className="flex flex-col gap-4" errors={errors} onSubmit={submit}>
        {message && (
          <Alert variant="error">
            <AlertDescription>{message}</AlertDescription>
          </Alert>
        )}
        <TextField
          name="email"
          label="Email"
          type="email"
          autoComplete="email"
          defaultValue={email}
          required
        />
        <TextField
          name="code"
          label="Invitation code"
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={6}
          pattern="[0-9]{6}"
          required
        />
        <TextField
          name="password"
          label="Password"
          type="password"
          autoComplete="new-password"
          minLength={8}
          description="At least 8 characters, with upper and lower case letters and a number."
          required
        />
        <TextField
          name="password_confirmation"
          label="Confirm password"
          type="password"
          autoComplete="new-password"
          minLength={8}
          required
        />
        <Button type="submit" loading={busy}>Set password</Button>
        <Link className="text-center text-sm underline underline-offset-4" to="/sign-in">
          I already have an account
        </Link>
      </Form>
    </AuthCard>
  )
}
