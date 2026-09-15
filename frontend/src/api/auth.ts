import {
  clearCartToken,
  getCartToken,
  setCartToken,
} from '@/api/cart'

export type AuthUser = {
  id: number
  firstName: string
  lastName?: string
  displayName?: string
  email: string
}

type AuthResponse = {
  authenticated: boolean
  user: AuthUser | null
  nonce: string | null
  cartToken?: string | null
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

const CUSTOMER_SESSION_KEY = 'pupilovo-customer-id'
let restNonce: string | null = null

function clearCustomerSession() {
  restNonce = null
  sessionStorage.removeItem(CUSTOMER_SESSION_KEY)
  clearCartToken()
}

export function expireCustomerSession(): AuthError {
  clearCustomerSession()
  // A full navigation also discards account, address and cart component state.
  window.location.assign('/account?session=expired')
  return new AuthError('Sesja wygasła lub zmieniła się. Zaloguj się ponownie.', 'session_expired')
}

async function parseResponse(response: Response): Promise<AuthResponse> {
  const data = await response.json()

  if (!response.ok) {
    throw new AuthError(
      data?.message || 'Wystąpił błąd podczas komunikacji z serwerem.',
      typeof data?.code === 'string' ? data.code : 'auth_error',
    )
  }

  restNonce = data.nonce ?? null

  if (data.authenticated && data.user) {
    sessionStorage.setItem(CUSTOMER_SESSION_KEY, String(data.user.id))
  }

  if (typeof data.cartToken === 'string') {
    setCartToken(data.cartToken)
  }

  return data
}

export async function getCurrentUser(): Promise<AuthResponse> {
  const previousCustomer = sessionStorage.getItem(CUSTOMER_SESSION_KEY)
  const response = await fetch('/wp-json/pupilovo/v1/auth/me', {
    credentials: 'include',
    cache: 'no-store',
  })

  if (response.status === 401 || response.status === 403) {
    throw expireCustomerSession()
  }
  const session = await parseResponse(response)
  if (previousCustomer && (!session.authenticated || String(session.user?.id) !== previousCustomer)) {
    throw expireCustomerSession()
  }
  return session
}

async function authenticate(
  endpoint: 'login' | 'register' | 'google',
  payload: LoginPayload | RegisterPayload | { credential: string; password?: string },
): Promise<AuthResponse> {
  const headers = new Headers({
    'Content-Type': 'application/json',
  })

  const currentCartToken = getCartToken()

  if (currentCartToken) {
    headers.set('Cart-Token', currentCartToken)
  }

  const response = await fetch(`/wp-json/pupilovo/v1/auth/${endpoint}`, {
    method: 'POST',
    credentials: 'include',
    headers,
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
  const session = await getCurrentUser()
  if (!session.authenticated || !session.nonce) {
    clearCustomerSession()
    return
  }

  const response = await fetch('/wp-json/pupilovo/v1/auth/logout', {
    method: 'POST',
    credentials: 'include',
    headers: {
      'X-WP-Nonce': restNonce ?? '',
    },
  })

  if (response.status === 401 || response.status === 403) throw expireCustomerSession()
  await parseResponse(response)

  clearCustomerSession()
}

/** Reuse the cookie/nonce bootstrap for authenticated account requests. */
export async function getAccountData<T>(path: string, signal?: AbortSignal): Promise<T> {
  return accountRequest<T>(path, { signal })
}

export async function accountRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  if (!restNonce) {
    const session = await getCurrentUser()
    if (!session.authenticated || !session.nonce) {
      throw new AuthError('Sesja wygasła. Zaloguj się ponownie.', 'session_expired')
    }
  }
  const response = await fetch(path, {
    ...options,
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce ?? '' },
    cache: 'no-store',
  })
  const data = await response.json()
  if (!response.ok) {
    if (response.status === 401 || response.status === 403) throw expireCustomerSession()
    throw new AuthError(
      response.status === 401 || response.status === 403
        ? 'Sesja wygasła. Odśwież stronę i zaloguj się ponownie.'
        : data?.message || 'Nie udało się pobrać danych konta.',
      typeof data?.code === 'string' ? data.code : 'account_error',
    )
  }
  return data as T
}
