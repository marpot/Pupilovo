import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'

import LoginForm from '@/components/LoginForm/LoginForm'
import RegisterForm from '@/components/RegisterForm/RegisterForm'
import AccountDashboard from '@/components/AccountDashboard/AccountDashboard'
import '@/pages/Account/Account.scss'

function Account() {
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [searchParams] = useSearchParams()
  const preview = import.meta.env.DEV && searchParams.get('preview') === 'dashboard'

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' })
  }, [])

  return (
    <main className="account">
      <div className="account__container">
        <header className="account__heading">
          <p className="account__eyebrow">Twoje miejsce w Pupilovo</p>
          <h1>Moje konto</h1>
          <p>Wszystko dla Ciebie i Twojego pupila. W jednym miejscu.</p>
        </header>

        {preview ? (
          <>
            <p className="account__preview">Podgląd widoku konta — bez aktywnej sesji użytkownika.</p>
            <AccountDashboard />
            <Link className="account__preview-link" to="/account">Wróć do formularzy</Link>
          </>
        ) : (
          <div className="account__layout">
            <aside className="account__intro">
              <span className="account__mark" aria-hidden="true">♡</span>
              <h2>Blisko Ciebie.<br />Jeszcze bliżej pupila.</h2>
              <p>Twoje konto pomoże Ci wygodnie zadbać o codzienne zakupy.</p>
              <ul>
                <li>Historia zamówień w jednym miejscu</li>
                <li>Adresy pod ręką przy kolejnych zakupach</li>
                <li>Łatwy dostęp do swoich danych</li>
              </ul>
              <p className="account__availability">Konta klientów będą dostępne wkrótce.</p>
            </aside>

            <section className="account__card" aria-label="Dostęp do konta">
              <div className="account__switch" role="group" aria-label="Wybierz formularz">
                <button type="button" aria-pressed={mode === 'login'} onClick={() => setMode('login')}>Logowanie</button>
                <button type="button" aria-pressed={mode === 'register'} onClick={() => setMode('register')}>Rejestracja</button>
              </div>
              {mode === 'login' ? <LoginForm /> : <RegisterForm />}
            </section>
          </div>
        )}

        {import.meta.env.DEV && !preview && (
          <Link className="account__preview-link" to="/account?preview=dashboard">Podgląd developerski: panel konta</Link>
        )}
      </div>
    </main>
  )
}

export default Account
