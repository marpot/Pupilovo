import { useEffect, useState, type FormEvent } from 'react'
import { getCustomerProfile, saveCustomerProfile, type CustomerProfile } from '@/api/profile'

export default function AccountProfile({ fallback }: { fallback: { firstName: string; email: string } }) {
  const [profile, setProfile] = useState<CustomerProfile>({ firstName: fallback.firstName, lastName: '', displayName: fallback.firstName, email: fallback.email })
  const [editing, setEditing] = useState(false)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  useEffect(() => { const controller = new AbortController(); getCustomerProfile(controller.signal).then((data) => { if (!controller.signal.aborted) setProfile(data) }).catch(() => undefined).finally(() => { if (!controller.signal.aborted) setLoading(false) }); return () => controller.abort() }, [])
  async function submit(event: FormEvent) { event.preventDefault(); setSaving(true); setError(''); setMessage(''); try { setProfile(await saveCustomerProfile(profile)); setEditing(false); setMessage('Dane konta zostały zapisane.') } catch (e) { setError(e instanceof Error ? e.message : 'Nie udało się zapisać danych.') } finally { setSaving(false) } }
  if (loading) return <p role="status">Ładowanie danych konta…</p>
  return <div className="account-profile">{editing ? <form onSubmit={(event) => void submit(event)}>
    {(['firstName', 'lastName', 'displayName'] as const).map((field) => <label key={field}>{field === 'firstName' ? 'Imię' : field === 'lastName' ? 'Nazwisko' : 'Nazwa wyświetlana'}<input required={field !== 'lastName'} value={profile[field]} onChange={(event) => setProfile({ ...profile, [field]: event.target.value })} maxLength={100} /></label>)}
    <p>E-mail: {profile.email}</p><button disabled={saving} type="submit">{saving ? 'Zapisywanie…' : 'Zapisz dane'}</button> <button disabled={saving} type="button" onClick={() => setEditing(false)}>Anuluj</button>
  </form> : <><dl><dt>Imię</dt><dd>{profile.firstName || '—'}</dd><dt>Nazwisko</dt><dd>{profile.lastName || '—'}</dd><dt>Nazwa wyświetlana</dt><dd>{profile.displayName || '—'}</dd><dt>E-mail</dt><dd>{profile.email}</dd></dl><button type="button" onClick={() => { setEditing(true); setMessage('') }}>Edytuj dane</button></>}{error && <p role="alert">{error}</p>}{message && <p role="status">{message}</p>}</div>
}
