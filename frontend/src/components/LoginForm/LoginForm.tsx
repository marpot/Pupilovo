import { useState } from 'react'
import type { FormEvent } from 'react'

import { loginCustomer } from '@/api/auth'
import type { AuthUser } from '@/api/auth'
import '@/components/AccountForm/AccountForm.scss'

type LoginFormProps = {
  onAuthenticated: (user: AuthUser) => void
}

function LoginForm({ onAuthenticated }: LoginFormProps) {
  const [message, setMessage] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    const form = event.currentTarget
    const formData = new FormData(form)

    const email = String(formData.get('email') ?? '')
    const password = String(formData.get('password') ?? '')

    setMessage('')
    setIsSubmitting(true)

    try {
      const response = await loginCustomer({
        email,
        password,
      })

      if (!response.user) {
        throw new Error('Nie udało się pobrać danych użytkownika.')
      }

      onAuthenticated(response.user)
    } catch (error) {
      setMessage(
        error instanceof Error
          ? error.message
          : 'Nie udało się zalogować.',
      )
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <form
      className="account-form"
      onSubmit={handleSubmit}
      aria-labelledby="login-title"
    >
      <div>
        <h2 id="login-title">Dobrze Cię widzieć</h2>
        <p>Zaloguj się do swojego konta Pupilovo.</p>
      </div>

      <div className="account-form__field">
        <label htmlFor="login-email">E-mail</label>
        <input
          id="login-email"
          name="email"
          type="email"
          autoComplete="username"
          required
        />
      </div>

      <div className="account-form__field">
        <label htmlFor="login-password">Hasło</label>
        <input
          id="login-password"
          name="password"
          type="password"
          autoComplete="current-password"
          required
        />
      </div>

      <button
        className="account-form__submit"
        type="submit"
        disabled={isSubmitting}
      >
        {isSubmitting ? 'Logowanie...' : 'Zaloguj się'}
      </button>

      <p className="account-form__status" role="status">
        {message}
      </p>
    </form>
  )
}

export default LoginForm