import { useEffect, useState } from 'react'

import {
  getCurrentUser,
  logoutCustomer,
} from '@/api/auth'
import type { AuthUser } from '@/api/auth'
import AccountDashboard from '@/components/AccountDashboard/AccountDashboard'
import GoogleLoginButton from '@/components/GoogleLoginButton/GoogleLoginButton'
import LoginForm from '@/components/LoginForm/LoginForm'
import RegisterForm from '@/components/RegisterForm/RegisterForm'
import '@/pages/Account/Account.scss'

function Account() {
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [user, setUser] = useState<AuthUser | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [message, setMessage] = useState('')

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' })

    const loadSession = async () => {
      try {
        const response = await getCurrentUser()

        setUser(response.authenticated ? response.user : null)
      } catch {
        setUser(null)
      } finally {
        setIsLoading(false)
      }
    }

    void loadSession()
  }, [])

  const handleAuthenticated = (authenticatedUser: AuthUser) => {
    setUser(authenticatedUser)
    setMessage('')
  }

  const handleLogout = async () => {
    setMessage('')

    try {
      await logoutCustomer()
      setUser(null)
      setMode('login')
    } catch (error) {
      setMessage(
        error instanceof Error
          ? error.message
          : 'Nie udało się wylogować.',
      )
    }
  }

  return (
    <main className="account">
      <div className="account__container">
        <header className="account__heading">
          <p className="account__eyebrow">
            Twoje miejsce w Pupilovo
          </p>

          <h1>Moje konto</h1>

          <p>
            Wszystko dla Ciebie i Twojego pupila. W jednym miejscu.
          </p>
        </header>

        {isLoading ? (
          <p className="account__status">
            Ładowanie konta...
          </p>
        ) : user ? (
          <>
            <AccountDashboard
              user={user}
              onLogout={() => void handleLogout()}
            />

            {message && (
              <p className="account__status" role="status">
                {message}
              </p>
            )}
          </>
        ) : (
          <div className="account__layout">
            <aside className="account__intro">
              <span
                className="account__mark"
                aria-hidden="true"
              >
                ♡
              </span>

              <h2>
                Blisko Ciebie.
                <br />
                Jeszcze bliżej pupila.
              </h2>

              <p>
                Twoje konto pomoże Ci wygodnie zadbać
                o codzienne zakupy.
              </p>

              <ul>
                <li>Historia zamówień w jednym miejscu</li>
                <li>Adresy pod ręką przy kolejnych zakupach</li>
                <li>Łatwy dostęp do swoich danych</li>
              </ul>
            </aside>

            <section
              className="account__card"
              aria-label="Dostęp do konta"
            >
              <div
                className="account__switch"
                role="group"
                aria-label="Wybierz formularz"
              >
                <button
                  type="button"
                  aria-pressed={mode === 'login'}
                  onClick={() => setMode('login')}
                >
                  Logowanie
                </button>

                <button
                  type="button"
                  aria-pressed={mode === 'register'}
                  onClick={() => setMode('register')}
                >
                  Rejestracja
                </button>
              </div>

              {mode === 'login' ? (
                <LoginForm
                  onAuthenticated={handleAuthenticated}
                />
              ) : (
                <RegisterForm
                  onAuthenticated={handleAuthenticated}
                />
              )}
              <GoogleLoginButton onAuthenticated={handleAuthenticated} />
            </section>
          </div>
        )}
      </div>
    </main>
  )
}

export default Account