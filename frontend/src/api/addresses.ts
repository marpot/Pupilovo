import { accountRequest, getAccountData } from '@/api/auth'

export type CustomerAddress = {
  first_name: string
  last_name: string
  company: string
  address_1: string
  address_2: string
  city: string
  postcode: string
  country: string
  state: string
  phone: string
  email?: string
}
export type AddressType = 'billing' | 'shipping'
export type CustomerAddresses = Record<AddressType, CustomerAddress>

export const emptyAddress = (): CustomerAddress => ({
  first_name: '', last_name: '', company: '', address_1: '', address_2: '',
  city: '', postcode: '', country: 'PL', state: '', phone: '',
})

export const getCustomerAddresses = (signal?: AbortSignal) =>
  getAccountData<CustomerAddresses>('/wp-json/pupilovo/v1/account/addresses', signal)

export const saveCustomerAddress = (type: AddressType, address: CustomerAddress) =>
  accountRequest<CustomerAddresses>(`/wp-json/pupilovo/v1/account/addresses/${type}`, {
    method: 'POST', body: JSON.stringify(address),
  })
