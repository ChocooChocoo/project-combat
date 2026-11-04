import { useState } from 'react'
import { useNavigate } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
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

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Profile" description="Your details, photo and password." />
      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Your details</CardTitle>
            <CardDescription>Changing your email means confirming the new one.</CardDescription>
          </CardHeader>
          <CardPanel className="flex flex-col gap-4">
            <div className="flex items-center gap-3">
              <Avatar className="size-14">
                {user.photo_url && <AvatarImage src={user.photo_url} alt="" />}
                <AvatarFallback>{initials}</AvatarFallback>
              </Avatar>
              <label className="cursor-pointer text-sm underline">
                Change photo
                <input type="file" accept="image/*" className="sr-only" onChange={uploadPhoto} />
              </label>
            </div>
            <Form className="flex flex-col gap-4" errors={detailErrors} onSubmit={saveDetails}>
              <div className="grid grid-cols-2 gap-3">
                <TextField name="first_name" label="First name" maxLength={100} defaultValue={user.first_name} required />
                <TextField name="last_name" label="Last name" maxLength={100} defaultValue={user.last_name} required />
              </div>
              <TextField name="email" label="Email" type="email" maxLength={255} defaultValue={user.email} required />
              <TextField name="phone" label="Phone" type="tel" maxLength={30} validate={phoneRule} defaultValue={user.phone ?? ''} />
              <Button type="submit" loading={busy === 'details'}>Save details</Button>
            </Form>
          </CardPanel>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Password</CardTitle>
          </CardHeader>
          <CardPanel>
            <Form className="flex flex-col gap-4" errors={passwordErrors} onSubmit={changePassword}>
              <TextField name="current_password" label="Current password" type="password" autoComplete="current-password" required />
              <TextField name="password" label="New password" type="password" autoComplete="new-password" validate={passwordRule} required />
              <TextField name="password_confirmation" label="Confirm new password" type="password" autoComplete="new-password" validate={sameAs('password')} required />
              <Button type="submit" loading={busy === 'password'}>Change password</Button>
            </Form>
          </CardPanel>
        </Card>
      </div>
    </div>
  )
}
