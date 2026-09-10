import { useState } from 'react'
import type { FormEvent } from 'react'

import '@/components/AccountForm/AccountForm.scss'

function RegisterForm() {
  const [message, setMessage] = useState('')

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setMessage('Rejestracja nie jest jeszcze dostępna. Konto nie zostało utworzone, a Twoje dane nie zostały wysłane.')
  }

  const validatePasswords = (form: HTMLFormElement) => {
    const password = form.elements.namedItem('password') as HTMLInputElement
    const confirmation = form.elements.namedItem('passwordConfirmation') as HTMLInputElement
    confirmation.setCustomValidity(
      confirmation.value && password.value !== confirmation.value
        ? 'Hasła muszą być takie same.'
        : '',
    )
  }

  return (
    <form className="account-form" onSubmit={handleSubmit} onInput={(event) => validatePasswords(event.currentTarget)} aria-labelledby="register-title">
      <div>
        <h2 id="register-title">Dołącz do Pupilovo</h2>
        <p>Stwórz miejsce dla siebie i swojego pupila.</p>
      </div>
      <div className="account-form__field">
        <label htmlFor="register-name">Imię</label>
        <input id="register-name" name="firstName" type="text" autoComplete="given-name" required />
      </div>
      <div className="account-form__field">
        <label htmlFor="register-email">E-mail</label>
        <input id="register-email" name="email" type="email" autoComplete="email" required />
      </div>
      <div className="account-form__field">
        <label htmlFor="register-password">Hasło</label>
        <input id="register-password" name="password" type="password" autoComplete="new-password" required />
      </div>
      <div className="account-form__field">
        <label htmlFor="register-confirmation">Potwierdź hasło</label>
        <input id="register-confirmation" name="passwordConfirmation" type="password" autoComplete="new-password" required />
      </div>
      <p className="account-form__note">Rejestracja będzie dostępna wkrótce. Formularz nie wysyła danych.</p>
      <button className="account-form__submit" type="submit">Utwórz konto</button>
      <p className="account-form__status" role="status">{message}</p>
    </form>
  )
}

export default RegisterForm
