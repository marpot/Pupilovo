import { expireCustomerSession, getCurrentUser } from '@/api/auth'
import type { WooCommerceCart } from '@/types/woocommerce'

const CART_API_URL = '/wp-json/wc/store/v1/cart'

export const CART_UPDATED_EVENT = 'pupilovo:cart-updated'

let cartToken: string | null =
  sessionStorage.getItem('pupilovo-cart-token')

export const setCartToken = (token: string | null) => {
  cartToken = token

  if (token) {
    sessionStorage.setItem('pupilovo-cart-token', token)
  } else {
    sessionStorage.removeItem('pupilovo-cart-token')
  }
}

export const clearCartToken = () => {
  setCartToken(null)
  window.dispatchEvent(new CustomEvent(CART_UPDATED_EVENT, { detail: { count: 0 } }))
}

const saveCartToken = (response: Response) => {
  const token = response.headers.get('Cart-Token')

  if (token) {
    setCartToken(token)
  }
}

const notifyCartUpdated = (cart: WooCommerceCart) => {
  window.dispatchEvent(
    new CustomEvent(CART_UPDATED_EVENT, {
      detail: {
        count: cart.items_count,
      },
    }),
  )
}

const cartRequest = async (
  endpoint = '',
  options: RequestInit = {},
): Promise<WooCommerceCart> => {
  const headers = new Headers(options.headers)

  headers.set('Content-Type', 'application/json')

  if (cartToken) {
    headers.set('Cart-Token', cartToken)
  }

  const session = await getCurrentUser()

  if (session.authenticated && session.nonce) {
    headers.set('X-WP-Nonce', session.nonce)
  }

  const response = await fetch(`${CART_API_URL}${endpoint}`, {
    ...options,
    headers,
  })

  saveCartToken(response)

  if (response.status === 401 || response.status === 403) {
    throw expireCustomerSession()
  }

  if (!response.ok) {
    const data = await response.json().catch(() => null)

    const message =
      typeof data?.message === 'string'
        ? data.message
        : `WooCommerce Cart API error: ${response.status} ${response.statusText}`

    throw new Error(message)
  }

  const cart = await response.json() as WooCommerceCart

  notifyCartUpdated(cart)

  return cart
}

export const getCart = async (
  signal?: AbortSignal,
) =>
  cartRequest('', {
    method: 'GET',
    signal,
  })

export const addCartItem = async (
  productId: number,
  quantity = 1,
) =>
  cartRequest('/add-item', {
    method: 'POST',
    body: JSON.stringify({
      id: productId,
      quantity,
    }),
  })

export const updateCartItem = async (
  key: string,
  quantity: number,
) =>
  cartRequest('/update-item', {
    method: 'POST',
    body: JSON.stringify({
      key,
      quantity,
    }),
  })

export const removeCartItem = async (
  key: string,
) =>
  cartRequest('/remove-item', {
    method: 'POST',
    body: JSON.stringify({
      key,
    }),
  })

export interface CartCustomerAddress {
  first_name: string
  last_name: string
  company: string
  address_1: string
  address_2: string
  city: string
  state: string
  postcode: string
  country: string
  email?: string
  phone?: string
}

export const updateCartCustomer = async (
  billingAddress: CartCustomerAddress,
  shippingAddress: CartCustomerAddress,
) =>
  cartRequest('/update-customer', {
    method: 'POST',
    body: JSON.stringify({
      billing_address: billingAddress,
      shipping_address: shippingAddress,
    }),
  })

export const selectShippingRate = async (
  packageId: number,
  rateId: string,
) =>
  cartRequest('/select-shipping-rate', {
    method: 'POST',
    body: JSON.stringify({
      package_id: packageId,
      rate_id: rateId,
    }),
  })

export const getCartToken = () => cartToken