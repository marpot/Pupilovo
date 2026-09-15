import { useEffect, useState, type FormEvent } from 'react'
import { getCustomerAddresses, saveCustomerAddress, type AddressType, type CustomerAddress, type CustomerAddresses } from '@/api/addresses'
import AddressFields from '@/components/CustomerAddresses/AddressFields'

function AddressCard({ type, saved }: { type: AddressType; saved: CustomerAddress }) {
  const [address, setAddress] = useState(saved)
  const [draft, setDraft] = useState(saved)
  const [editing, setEditing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  async function save(event: FormEvent) {
    event.preventDefault()
    setSaving(true); setError(''); setSuccess('')
    try {
      const result = await saveCustomerAddress(type, draft)
      setAddress(result[type]); setDraft(result[type]); setEditing(false)
      setSuccess('Adres został zapisany.')
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Nie udało się zapisać adresu.')
    } finally { setSaving(false) }
  }

  return <section className="customer-addresses__card" aria-busy={saving}>
    {editing ? <form onSubmit={(event) => void save(event)}>
      <AddressFields type={type} value={draft} onChange={setDraft} disabled={saving} />
      <div className="customer-addresses__actions">
        <button disabled={saving} type="submit">{saving ? 'Zapisywanie…' : 'Zapisz adres'}</button>
        <button disabled={saving} type="button" onClick={() => { setEditing(false); setError('') }}>Anuluj</button>
      </div>
    </form> : <>
      <h3>{type === 'billing' ? 'Adres rozliczeniowy' : 'Adres dostawy'}</h3>
      {Object.entries(address).some(([field, value]) => field !== 'country' && value.trim()) ? <address>
        {[`${address.first_name} ${address.last_name}`, address.company, address.address_1, address.address_2,
          `${address.postcode} ${address.city}`, `${address.country} ${address.state}`, address.phone, address.email]
          .filter((line) => line?.trim()).map((line, index) => <div key={index}>{line}</div>)}
      </address> : <p>Nie zapisano jeszcze adresu.</p>}
      <button type="button" onClick={() => { setDraft({ ...address, country: address.country || 'PL' }); setEditing(true); setSuccess('') }}>Edytuj adres</button>
    </>}
    {error && <p role="alert">{error}</p>}
    {success && <p role="status">{success}</p>}
  </section>
}

export default function CustomerAddresses() {
  const [addresses, setAddresses] = useState<CustomerAddresses | null>(null)
  const [error, setError] = useState('')
  const [attempt, setAttempt] = useState(0)
  useEffect(() => {
    const controller = new AbortController()
    getCustomerAddresses(controller.signal).then((data) => {
      if (!controller.signal.aborted) setAddresses(data)
    }).catch((error: unknown) => {
      if (!controller.signal.aborted) setError(error instanceof Error ? error.message : 'Nie udało się pobrać adresów.')
    })
    return () => controller.abort()
  }, [attempt])
  if (error) return <div><p role="alert">{error}</p><button type="button" onClick={() => { setError(''); setAttempt(attempt + 1) }}>Spróbuj ponownie</button></div>
  if (!addresses) return <p role="status">Ładowanie adresów…</p>
  return <div className="customer-addresses">{(['billing', 'shipping'] as const).map((type) => <AddressCard key={type} type={type} saved={addresses[type]} />)}</div>
}
