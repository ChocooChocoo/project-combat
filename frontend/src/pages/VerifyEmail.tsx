import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { AuthCard } from '@/components/auth-card'
import { CodeInput } from '@/components/code-input'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { api, ApiError } from '@/lib/api'
import { useAuth, type User } from '@/lib/auth'
import { homePath } from '@/lib/gym-access'

/** S-06, R-02: enter the emailed code. A correct code signs the person in and lands them home. */
export default function VerifyEmail() {
  const [params] = useSearchParams()
  const { user, signIn } = useAuth()
  const navigate = useNavigate()
  const email = params.get('email') ?? user?.email ?? ''
  const [code, setCode] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState(false)

  async function verify() {
    setBusy(true)
    setError('')
    try {
      const result = await api<{ token: string; user: User }>('/auth/verify', { body: { email, code } })
      signIn(result.token, result.user)
      navigate(homePath(result.user), { replace: true })
    } catch (e) {
      setError(e instanceof ApiError ? (e.errors.code?.[0] ?? e.message) : 'Something went wrong.')
      setCode('')
    } finally {
      setBusy(false)
    }
  }

  async function resend() {
    const result = await api<{ message: string }>('/auth/resend-code', { body: { email } })
    setNotice(result.message)
  }

  return (
    <AuthCard
      title="Confirm your email"
      description={<>We sent a 6-digit code to <strong>{email || 'your email'}</strong>.</>}
    >
      <div className="flex flex-col items-center gap-4">
        <CodeInput value={code} onChange={setCode} />
        {error && <Alert variant="error"><AlertDescription>{error}</AlertDescription></Alert>}
        {notice && <Alert variant="info"><AlertDescription>{notice}</AlertDescription></Alert>}
        <Button className="w-full" disabled={code.length !== 6} loading={busy} onClick={verify}>Confirm</Button>
        <Button variant="link" onClick={resend} disabled={!email}>Send a new code</Button>
      </div>
    </AuthCard>
  )
}
