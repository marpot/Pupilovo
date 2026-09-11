import { useState } from 'react'

import OrderHistory from '@/components/OrderHistory/OrderHistory'

import '@/components/AccountDashboard/AccountDashboard.scss'

type AccountDashboardProps = {
  user?: { firstName: string; email: string }
  onLogout?: () => void
}

const sections = [
  { id: 'details', label: 'Dane konta' },
  { id: 'orders', label: 'Zamówienia' },
  { id: 'addresses', label: 'Adresy' },
] as const

function AccountDashboard({ user, onLogout }: AccountDashboardProps) {
  const [section, setSection] = useState<typeof sections[number]['id']>('details')

  return (
    <div className="account-dashboard">
      <nav className="account-dashboard__nav" aria-label="Sekcje konta">
        {sections.map((item) => (
          <button key={item.id} type="button" aria-current={section === item.id ? 'true' : undefined} onClick={() => setSection(item.id)}>{item.label}</button>
        ))}
        <button type="button" onClick={onLogout} disabled={!onLogout}>Wyloguj się</button>
      </nav>
      <section className="account-dashboard__content" aria-live="polite" aria-atomic="true">
        <h2>{sections.find((item) => item.id === section)?.label}</h2>
        {section === 'details' && (
          <>
            <p>Twoje podstawowe dane w Pupilovo.</p>
            <dl>
              <dt>Imię</dt><dd>{user?.firstName || '—'}</dd>
              <dt>E-mail</dt><dd>{user?.email || '—'}</dd>
            </dl>
            <p className="account-dashboard__note">Edycja danych będzie dostępna wkrótce.</p>
          </>
        )}
        {section === 'orders' && <OrderHistory />}
        {section === 'addresses' && <p>Tu zarządzisz adresami dostawy i rozliczeń. Ta sekcja będzie dostępna wkrótce.</p>}
      </section>
    </div>
  )
}

export default AccountDashboard
