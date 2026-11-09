import { useState } from 'react'
import { useNavigate } from 'react-router'
import { TextField } from '@/components/text-field'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Form } from '@/components/ui/form'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { useAuth, type User } from '@/lib/auth'
import { fileProblem, passwordRule, phoneRule, sameAs } from '@/lib/validation'

/** S-14, R-05: own details, photo and password. */
export default function Profile() {
  const { user, setUser } = useAuth()
  const navigate = useNavigate()
  const [detailErrors, setDetailErrors] = useState<FieldErrors>({})
  const [passwordErrors, setPasswordErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState('')

  if (!user) return null

  async function saveDetails(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    setBusy('details')
    try {
      const result = await api<{ data: User }>('/me', { method: 'PUT', body: values })
      setUser(result.data)
      setDetailErrors({})
      if (!result.data.email_verified) {
        navigate(`/verify-email?email=${encodeURIComponent(result.data.email)}`)
        return
      }
      toastManager.add({ title: 'Details saved.', type: 'success' })
    } catch (error) {
      setDetailErrors(error instanceof ApiError ? error.errors : {})
    } finally {
      setBusy('')
    }
  }

  async function uploadPhoto(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    const problem = fileProblem(file, 'image/*', 2048)
    if (problem) {
      toastManager.add({ title: problem, type: 'error' })
      return
    }
    const body = new FormData()
    body.append('photo', file)
    try {
      setUser((await api<{ data: User }>('/me/photo', { body })).data)
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'Upload failed.', type: 'error' })
    }
  }

  async function changePassword(event: React.FormEvent<HTMLFormElement>) {
    const form = event.currentTarget
    const values = formValues(event)
    setBusy('password')
    try {
      await api('/me/password', { method: 'PUT', body: values })
      setPasswordErrors({})
      form.reset()
      toastManager.add({ title: 'Password changed.', type: 'success' })
    } catch (error) {
      setPasswordErrors(error instanceof ApiError ? error.errors : {})
    } finally {
      setBusy('')
    }
  }

  const initials = `${user.first_name[0] ?? ''}${user.last_name[0] ?? ''}`
  const accountType = user.account_type.replace(/_/g, ' ')

  return (
    <div className="flex min-w-0 flex-col gap-8">
      <header className="flex flex-col gap-3 border-b pb-6">
        <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">Profile</h1>
        <p className="max-w-prose text-muted-foreground text-sm leading-relaxed">Your details, photo and password.</p>
      </header>

      <section className="flex flex-wrap items-center gap-x-6 gap-y-5 rounded-xl border bg-card p-5 sm:p-6" aria-label="Your account">
        <Avatar className="size-20 shrink-0">
          {user.photo_url && <AvatarImage src={user.photo_url} alt="" />}
          <AvatarFallback className="text-xl">{initials}</AvatarFallback>
        </Avatar>
        <div className="flex min-w-0 flex-1 flex-col gap-2">
          <h2 className="font-semibold text-2xl tracking-tight wrap-anywhere">{user.first_name} {user.last_name}</h2>
          <p className="text-muted-foreground text-sm wrap-anywhere">{user.email}</p>
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant={user.email_verified ? 'success' : 'warning'}>{user.email_verified ? 'Email verified' : 'Email not verified'}</Badge>
            <Badge variant="outline" className="capitalize">{accountType}</Badge>
          </div>
        </div>
        <label className="inline-flex min-h-11 cursor-pointer items-center rounded-lg border bg-background px-3.5 font-medium text-sm transition-colors hover:bg-accent focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-1 pointer-coarse:min-h-11">
          Change photo
          <input type="file" accept="image/*" className="sr-only" onChange={uploadPhoto} />
        </label>
      </section>

      <div className="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <Card className="min-w-0">
          <CardHeader>
            <CardTitle render={<h2 />} className="text-xl tracking-tight">Your details</CardTitle>
            <CardDescription>Changing your email means confirming the new one.</CardDescription>
          </CardHeader>
          <CardPanel>
            <Form className="flex flex-col gap-5" errors={detailErrors} onSubmit={saveDetails}>
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField name="first_name" label="First name" maxLength={100} defaultValue={user.first_name} required autoComplete="given-name" />
                <TextField name="last_name" label="Last name" maxLength={100} defaultValue={user.last_name} required autoComplete="family-name" />
              </div>
              <TextField name="email" label="Email" type="email" maxLength={255} defaultValue={user.email} required autoComplete="email" />
              <TextField name="phone" label="Phone" type="tel" maxLength={30} validate={phoneRule} defaultValue={user.phone ?? ''} autoComplete="tel" />
              <div className="flex justify-end">
                <Button type="submit" loading={busy === 'details'}>Save details</Button>
              </div>
            </Form>
          </CardPanel>
        </Card>

        <Card className="min-w-0">
          <CardHeader>
            <CardTitle render={<h2 />} className="text-xl tracking-tight">Password</CardTitle>
            <CardDescription>Use a password only you know.</CardDescription>
          </CardHeader>
          <CardPanel>
            <Form className="flex flex-col gap-5" errors={passwordErrors} onSubmit={changePassword}>
              <TextField name="current_password" label="Current password" type="password" autoComplete="current-password" required />
              <TextField name="password" label="New password" type="password" autoComplete="new-password" validate={passwordRule} required />
              <TextField name="password_confirmation" label="Confirm new password" type="password" autoComplete="new-password" validate={sameAs('password')} required />
              <Button type="submit" variant="outline" loading={busy === 'password'}>Change password</Button>
            </Form>
          </CardPanel>
        </Card>
      </div>
    </div>
  )
}
