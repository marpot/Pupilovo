import type { AddressType, CustomerAddress } from '@/api/addresses'
import '@/components/CustomerAddresses/CustomerAddresses.scss'

const fields = [
  ['first_name', 'Imię', 'given-name'], ['last_name', 'Nazwisko', 'family-name'],
  ['company', 'Firma (opcjonalnie)', 'organization'],
  ['address_1', 'Ulica i numer', 'address-line1'],
  ['address_2', 'Lokal / dodatkowy adres (opcjonalnie)', 'address-line2'],
  ['city', 'Miejscowość', 'address-level2'], ['postcode', 'Kod pocztowy', 'postal-code'],
  ['country', 'Kod kraju (np. PL)', 'country'],
  ['state', 'Kod regionu (jeśli wymagany, np. CA dla USA)', 'address-level1'],
  ['phone', 'Telefon', 'tel'], ['email', 'E-mail', 'email'],
] as const

export default function AddressFields({ type, value, onChange, disabled = false }: {
  type: AddressType
  value: CustomerAddress
  onChange: (value: CustomerAddress) => void
  disabled?: boolean
}) {
  return <fieldset className="address-fields" disabled={disabled}>
    <legend>{type === 'billing' ? 'Adres rozliczeniowy' : 'Adres dostawy'}</legend>
    {fields.filter(([field]) => type === 'billing' || field !== 'email').map(([field, label, autocomplete]) => (
      <label key={field}>
        {label}
        <input
          name={`${type}_${field}`} autoComplete={`${type} ${autocomplete}`}
          type={field === 'email' ? 'email' : field === 'phone' ? 'tel' : 'text'}
          required={['first_name', 'last_name', 'address_1', 'city', 'country', 'email'].includes(field)}
          maxLength={field === 'country' ? 2 : 500}
          value={value[field] ?? ''}
          onChange={(event) => onChange({ ...value, [field]: event.target.value })}
        />
      </label>
    ))}
  </fieldset>
}
