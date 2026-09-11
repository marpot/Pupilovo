import { useState } from 'react'
import type { FormEvent } from 'react'

import { registerCustomer } from '@/api/auth'
import type { AuthUser } from '@/api/auth'
import '@/components/AccountForm/AccountForm.scss'

type RegisterFormProps = {
  onAuthenticated: (user: AuthUser) => void
}

function RegisterForm({ onAuthenticated }: RegisterFormProps) {
  const [message, setMessage] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const validatePasswords = (form: HTMLFormElement) => {
    const password = form.elements.namedItem('password') as HTMLInputElement
    const confirmation = form.elements.namedItem(
      'passwordConfirmation',
    ) as HTMLInputElement

    confirmation.setCustomValidity(
      confirmation.value && password.value !== confirmation.value
        ? 'Hasła muszą być takie same.'
        : '',
    )
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    const form = event.currentTarget

    validatePasswords(form)

    if (!form.checkValidity()) {
      form.reportValidity()
      return
    }

    const formData = new FormData(form)

    const firstName = String(formData.get('firstName') ?? '')
    const email = String(formData.get('email') ?? '')
    const password = String(formData.get('password') ?? '')

    setMessage('')
    setIsSubmitting(true)

    try {
      const response = await registerCustomer({
        firstName,
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
          : 'Nie udało się utworzyć konta.',
      )
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <form
      className="account-form"
      onSubmit={handleSubmit}
      onInput={(event) => validatePasswords(event.currentTarget)}
      aria-labelledby="register-title"
    >
      <div>
        <h2 id="register-title">Dołącz do Pupilovo</h2>
        <p>Stwórz miejsce dla siebie i swojego pupila.</p>
      </div>

      <div className="account-form__field">
        <label htmlFor="register-name">Imię</label>
        <input
          id="register-name"
          name="firstName"
          type="text"
          autoComplete="given-name"
          required
        />
      </div>

      <div className="account-form__field">
        <label htmlFor="register-email">E-mail</label>
        <input
          id="register-email"
          name="email"
          type="email"
          autoComplete="email"
          required
        />
      </div>

      <div className="account-form__field">
        <label htmlFor="register-password">Hasło</label>
        <input
          id="register-password"
          name="password"
          type="password"
          autoComplete="new-password"
          minLength={8}
          required
        />
      </div>

      <div className="account-form__field">
        <label htmlFor="register-confirmation">Potwierdź hasło</label>
        <input
          id="register-confirmation"
          name="passwordConfirmation"
          type="password"
          autoComplete="new-password"
          minLength={8}
          required
        />
      </div>

      <button
        className="account-form__submit"
        type="submit"
        disabled={isSubmitting}
      >
        {isSubmitting ? 'Tworzenie konta...' : 'Utwórz konto'}
      </button>

      <p className="account-form__status" role="status">
        {message}
      </p>
    </form>
  )
}

export default RegisterForm