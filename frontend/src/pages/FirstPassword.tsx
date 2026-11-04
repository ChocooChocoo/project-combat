import { useState } from 'react'
import { useNavigate } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { passwordRule, sameAs } from '@/lib/validation'

/** R-06: an owner-created account must choose its own password before anything else. */
export default function FirstPassword() {
  const navigate = useNavigate()
  const { refresh, signOut } = useAuth()
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy(true)
    try {
      await api('/me/first-password', { body: values })
      await refresh()
      navigate('/', { replace: true })
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthCard title="Set your own password" description="Your account was created for you. Choose a new password to continue.">
      <Form className="flex flex-col gap-4" errors={errors} onSubmit={submit}>
        <TextField name="password" label="New password" type="password" autoComplete="new-password" validate={passwordRule} required />
        <TextField name="password_confirmation" label="Confirm new password" type="password" autoComplete="new-password" validate={sameAs('password')} required />
        <Button type="submit" loading={busy}>Save and continue</Button>
        <Button variant="ghost" onClick={signOut}>Sign out</Button>
      </Form>
    </AuthCard>
  )
}
