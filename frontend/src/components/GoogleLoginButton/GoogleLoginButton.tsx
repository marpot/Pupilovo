import { useEffect, useRef, useState } from 'react'

import { AuthError, loginWithGoogle } from '@/api/auth'
import type { AuthUser } from '@/api/auth'
import '@/components/GoogleLoginButton/GoogleLoginButton.scss'

type GoogleIdentity = {
  initialize: (options: {
    client_id: string
    nonce: string
    callback: (response: { credential: string }) => void
  }) => void
  renderButton: (element: HTMLElement, options: {
    type: 'standard'; theme: 'outline'; size: 'large'; locale: string
  }) => void
}

declare global {
  interface Window {
    google?: { accounts: { id: GoogleIdentity } }
  }
}

let scriptPromise: Promise<GoogleIdentity> | undefined

function loadGoogle(): Promise<GoogleIdentity> {
  if (window.google?.accounts.id) return Promise.resolve(window.google.accounts.id)
  if (scriptPromise) return scriptPromise
  scriptPromise = new Promise<GoogleIdentity>((resolve, reject) => {
    const script = document.createElement('script')
    const timeout = window.setTimeout(() => fail(), 15000)
    const fail = () => {
      window.clearTimeout(timeout)
      script.remove()
      reject(new Error('Nie udało się załadować Google. Odśwież stronę lub użyj e-maila i hasła.'))
    }
    script.src = 'https://accounts.google.com/gsi/client'
    script.async = true
    script.onload = () => {
      window.clearTimeout(timeout)
      if (window.google?.accounts.id) resolve(window.google.accounts.id)
      else fail()
    }
    script.onerror = fail
    document.head.appendChild(script)
  }).catch((error: unknown) => {
    scriptPromise = undefined
    throw error
  })
  return scriptPromise
}

function GoogleLoginButton({ onAuthenticated }: {
  onAuthenticated: (user: AuthUser) => void
}) {
  const container = useRef<HTMLDivElement>(null)
  const callback = useRef(onAuthenticated)
  const pending = useRef(false)
  const [status, setStatus] = useState('Ładowanie Google…')
  const [busy, setBusy] = useState(false)
  const [linkCredential, setLinkCredential] = useState<string | null>(null)
  const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID?.trim()

  useEffect(() => { callback.current = onAuthenticated }, [onAuthenticated])

  useEffect(() => {
    if (!clientId) return
    let active = true
    const element = container.current
    void loadGoogle().then((google) => {
      if (!active || !element) return
      const nonce = Array.from(crypto.getRandomValues(new Uint8Array(32)),
        (byte) => byte.toString(16).padStart(2, '0')).join('')
      document.cookie = `pupilovo_google_nonce=${nonce}; Path=/; SameSite=Strict; Max-Age=3600${location.protocol === 'https:' ? '; Secure' : ''}`
      google.initialize({
        client_id: clientId,
        nonce,
        callback: ({ credential }) => {
          if (!active || pending.current) return
          pending.current = true
          setBusy(true)
          setLinkCredential(null)
          setStatus('Logowanie przez Google…')
          void loginWithGoogle(credential).then((response) => {
            if (!response.authenticated || !response.user) {
              throw new Error('Nie udało się pobrać danych użytkownika.')
            }
            if (active) callback.current(response.user)
          }).catch((error: unknown) => {
            if (active && error instanceof AuthError && error.code === 'google_link_confirmation_required') {
              setLinkCredential(credential)
            }
            if (active) setStatus(error instanceof Error ? error.message : 'Nie udało się zalogować przez Google.')
          }).finally(() => {
            pending.current = false
            if (active) setBusy(false)
          })
        },
      })
      google.renderButton(element, { type: 'standard', theme: 'outline', size: 'large', locale: 'pl' })
      setStatus('')
    }).catch((error: unknown) => {
      if (active) setStatus(error instanceof Error ? error.message : 'Google jest niedostępne.')
    })
    return () => {
      active = false
      element?.replaceChildren()
    }
  }, [clientId])

  if (!clientId) return null
  return (
    <div className="google-login" aria-busy={busy}>
      <div className="google-login__separator">lub</div>
      <div ref={container} inert={busy} />
      <p role="status">{status}</p>
      {linkCredential && (
        <form className="account-form" onSubmit={(event) => {
          event.preventDefault()
          if (pending.current) return
          const password = String(new FormData(event.currentTarget).get('password') ?? '')
          event.currentTarget.reset()
          pending.current = true
          setBusy(true)
          void loginWithGoogle(linkCredential, password).then((response) => {
            if (!response.authenticated || !response.user) throw new Error('Nie udało się pobrać konta.')
            setLinkCredential(null)
            callback.current(response.user)
          }).catch((error: unknown) => {
            setStatus(error instanceof Error ? error.message : 'Nie udało się połączyć konta.')
            if (!(error instanceof AuthError) || error.code !== 'google_link_confirmation_required') {
              setLinkCredential(null)
            }
          }).finally(() => {
            pending.current = false
            setBusy(false)
          })
        }}>
          <div className="account-form__field">
            <label htmlFor="google-link-password">Hasło do konta Pupilovo</label>
            <input id="google-link-password" name="password" type="password" autoComplete="current-password" required disabled={busy} />
          </div>
          <button className="account-form__submit" type="submit" disabled={busy}>Potwierdź i połącz z Google</button>
          <button type="button" disabled={busy} onClick={() => { setLinkCredential(null); setStatus('') }}>Anuluj</button>
        </form>
      )}
    </div>
  )
}

export default GoogleLoginButton
