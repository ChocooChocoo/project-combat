import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { CodeInput } from '@/components/code-input'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'

/** S-08, R-04: ask for a code, then set a new password with it. */
export default function ForgotPassword() {
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [notice, setNotice] = useState('')
  const [code, setCode] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function requestCode(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy(true)
    try {
      const result = await api<{ message: string }>('/auth/forgot-password', { body: values })
      setEmail(values.email)
      setNotice(result.message)
    } finally {
      setBusy(false)
    }
  }

  async function reset(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy(true)
    try {
      await api('/auth/reset-password', { body: { ...values, email, code } })
      toastManager.add({ title: 'Password changed. Sign in with your new password.', type: 'success' })
      navigate('/sign-in')
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
    } finally {
      setBusy(false)
    }
  }

  if (!email) {
    return (
      <AuthCard title="Reset your password" description="Enter your email and we will send you a code.">
        <Form className="flex flex-col gap-4" onSubmit={requestCode}>
          <TextField name="email" label="Email" type="email" autoComplete="email" required />
          <Button type="submit" loading={busy}>Send code</Button>
          <Link className="text-center text-sm underline" to="/sign-in">Back to sign in</Link>
        </Form>
      </AuthCard>
    )
  }

  return (
    <AuthCard title="Choose a new password">
      <Form className="flex flex-col gap-4" errors={errors} onSubmit={reset}>
        <Alert variant="info"><AlertDescription>{notice}</AlertDescription></Alert>
        <div className="flex flex-col gap-2">
          <span className="font-medium text-sm">Code</span>
          <CodeInput value={code} onChange={setCode} />
          {errors.code && <p className="text-destructive-foreground text-xs">{errors.code[0]}</p>}
        </div>
        <TextField name="password" label="New password" type="password" autoComplete="new-password" required />
        <TextField name="password_confirmation" label="Confirm new password" type="password" autoComplete="new-password" required />
        <Button type="submit" loading={busy} disabled={code.length !== 6}>Change password</Button>
      </Form>
    </AuthCard>
  )
}
