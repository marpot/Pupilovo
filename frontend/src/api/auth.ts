export type AuthUser = {
  id: number
  firstName: string
  email: string
}

type AuthResponse = {
  authenticated: boolean
  user: AuthUser | null
  nonce: string | null
}

type LoginPayload = {
  email: string
  password: string
}

type RegisterPayload = {
  firstName: string
  email: string
  password: string
}

export class AuthError extends Error {
  code: string

  constructor(message: string, code: string) {
    super(message)
    this.code = code
  }
}

let restNonce: string | null = null

async function parseResponse(response: Response): Promise<AuthResponse> {
  const data = await response.json()

  if (!response.ok) {
    throw new AuthError(
      data?.message || 'Wystąpił błąd podczas komunikacji z serwerem.',
      typeof data?.code === 'string' ? data.code : 'auth_error',
    )
  }

  if (data.nonce) {
    restNonce = data.nonce
  }

  return data
}

export async function getCurrentUser(): Promise<AuthResponse> {
  const response = await fetch('/wp-json/pupilovo/v1/auth/me', {
    credentials: 'include',
  })

  return parseResponse(response)
}

async function authenticate(
  endpoint: 'login' | 'register' | 'google',
  payload: LoginPayload | RegisterPayload | { credential: string; password?: string },
): Promise<AuthResponse> {
  const response = await fetch(`/wp-json/pupilovo/v1/auth/${endpoint}`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  const session = await parseResponse(response)
  return session.authenticated ? getCurrentUser() : session
}

export function loginCustomer(payload: LoginPayload): Promise<AuthResponse> {
  return authenticate('login', payload)
}

export function registerCustomer(payload: RegisterPayload): Promise<AuthResponse> {
  return authenticate('register', payload)
}

export function loginWithGoogle(credential: string, password?: string): Promise<AuthResponse> {
  return authenticate('google', { credential, password })
}

export async function logoutCustomer(): Promise<void> {
  if (!restNonce) {
    const session = await getCurrentUser()

    if (!session.authenticated || !session.nonce) {
      return
    }
  }

  const response = await fetch('/wp-json/pupilovo/v1/auth/logout', {
    method: 'POST',
    credentials: 'include',
    headers: {
      'X-WP-Nonce': restNonce ?? '',
    },
  })

  await parseResponse(response)

  restNonce = null
}

/** Reuse the cookie/nonce bootstrap for authenticated account API reads. */
export async function getAccountData<T>(path: string, signal?: AbortSignal): Promise<T> {
  if (!restNonce) {
    const session = await getCurrentUser()
    if (!session.authenticated || !session.nonce) {
      throw new AuthError('Sesja wygasła. Zaloguj się ponownie.', 'session_expired')
    }
  }
  const response = await fetch(path, {
    credentials: 'include',
    headers: { 'X-WP-Nonce': restNonce ?? '' },
    signal,
    cache: 'no-store',
  })
  const data = await response.json()
  if (!response.ok) {
    if (response.status === 401 || response.status === 403) restNonce = null
    throw new AuthError(
      response.status === 401 || response.status === 403
        ? 'Sesja wygasła. Odśwież stronę i zaloguj się ponownie.'
        : data?.message || 'Nie udało się pobrać danych konta.',
      typeof data?.code === 'string' ? data.code : 'account_error',
    )
  }
  return data as T
}
