import { useState } from 'react'
import type { FormEvent } from 'react'

import '@/components/AccountForm/AccountForm.scss'

function LoginForm() {
  const [message, setMessage] = useState('')

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setMessage('Logowanie nie jest jeszcze dostępne. Twoje dane nie zostały wysłane.')
  }

  return (
    <form className="account-form" onSubmit={handleSubmit} aria-labelledby="login-title">
      <div>
        <h2 id="login-title">Dobrze Cię widzieć</h2>
        <p>Zaloguj się do swojego konta Pupilovo.</p>
      </div>
      <div className="account-form__field">
        <label htmlFor="login-email">E-mail</label>
        <input id="login-email" name="email" type="email" autoComplete="username" required />
      </div>
      <div className="account-form__field">
        <label htmlFor="login-password">Hasło</label>
        <input id="login-password" name="password" type="password" autoComplete="current-password" required />
      </div>
      <p className="account-form__note">Logowanie będzie dostępne wkrótce. Formularz nie wysyła danych.</p>
      <button className="account-form__submit" type="submit">Zaloguj się</button>
      <p className="account-form__status" role="status">{message}</p>
    </form>
  )
}

export default LoginForm
