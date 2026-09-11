import { getCartToken } from '@/api/cart'

const CHECKOUT_API_URL =
  '/wp-json/wc/store/v1/checkout'

export interface CheckoutAddress {
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

export interface CheckoutPayload {
  billing_address: CheckoutAddress
  shipping_address: CheckoutAddress
  customer_note: string
  payment_method: string
  payment_data: unknown[]
  expected_total?: string
}

export interface CheckoutResponse {
  order_id: number
  order_number?: string
  status: string
  order_key: string
  payment_result?: {
    payment_status: string
    payment_details: unknown[]
    redirect_url: string
  }
}

export const processCheckout = async (
  payload: CheckoutPayload,
): Promise<CheckoutResponse> => {
  const headers = new Headers({
    'Content-Type': 'application/json',
  })

  const cartToken = getCartToken()

  if (cartToken) {
    headers.set('Cart-Token', cartToken)
  }

  const response = await fetch(
    CHECKOUT_API_URL,
    {
      method: 'POST',
      headers,
      body: JSON.stringify(payload),
    },
  )

  const data = await response.json()

  if (!response.ok) {
    const message =
      typeof data?.message === 'string'
        ? data.message
        : 'Nie udało się złożyć zamówienia.'

    throw new Error(message)
  }

  return data as CheckoutResponse
}