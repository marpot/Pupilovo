import { accountRequest, getAccountData } from '@/api/auth'

export type CustomerProfile = { firstName: string; lastName: string; displayName: string; email: string }
export const getCustomerProfile = (signal?: AbortSignal) => getAccountData<CustomerProfile>('/wp-json/pupilovo/v1/account/profile', signal)
export const saveCustomerProfile = (profile: Pick<CustomerProfile, 'firstName' | 'lastName' | 'displayName'>) =>
  accountRequest<CustomerProfile>('/wp-json/pupilovo/v1/account/profile', { method: 'POST', body: JSON.stringify(profile) })
