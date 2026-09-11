import { getAccountData } from '@/api/auth'

export type CustomerOrder = {
  id: number
  number: string
  createdAt: string | null
  status: string
  statusLabel: string
  total: string
  currency: string
  items: { id: number; name: string; quantity: number }[]
}

export type OrderPage = {
  orders: CustomerOrder[]
  page: number
  total: number
  totalPages: number
}

export function getCustomerOrders(page: number, signal?: AbortSignal): Promise<OrderPage> {
  return getAccountData(`/wp-json/pupilovo/v1/account/orders?page=${page}`, signal)
}
