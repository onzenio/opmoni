export type ClientPersonType = 'company' | 'individual'
export type ClientStatus = 'active' | 'inactive'
export type TaxRegime = 'mei' | 'simple_national' | 'presumed_profit' | 'actual_profit' | 'other' | 'not_applicable'
export type DeadlineStatus = 'missing' | 'valid' | 'expiring' | 'expired'

export interface ClientCertificate {
  id: number
  subject: string
  serial_number: string
  valid_from: string
  valid_until: string
  original_filename: string
  status: DeadlineStatus
}

export interface ClientEcacPowerOfAttorney {
  id: number
  starts_at: string
  expires_at: string
  notes: string | null
  status: DeadlineStatus
}

export interface Client {
  id: number
  person_type: ClientPersonType | null
  tax_id: string | null
  name: string
  trade_name: string | null
  status: ClientStatus
  tax_regime: TaxRegime | null
  registration_status: string | null
  registration_status_date: string | null
  opened_at: string | null
  company_size: string | null
  legal_nature: string | null
  primary_activity: { code: string | null, description: string | null }
  address: {
    street_type: string | null
    street: string | null
    number: string | null
    complement: string | null
    district: string | null
    postal_code: string | null
    city: string | null
    state: string | null
  }
  email: string | null
  phone: string | null
  certificate: ClientCertificate | null
  certificate_status: DeadlineStatus
  ecac_power_of_attorney: ClientEcacPowerOfAttorney | null
  ecac_power_of_attorney_status: DeadlineStatus
  source_updated_at: string | null
  looked_up_at: string | null
  created_at: string
  updated_at: string
}

export interface CnpjPreview {
  tax_id: string
  name: string
  trade_name: string | null
  registration_status: string | null
  registration_status_date: string | null
  opened_at: string | null
  company_size: string | null
  legal_nature: string | null
  primary_activity_code: string | null
  primary_activity_description: string | null
  street_type: string | null
  street: string | null
  address_number: string | null
  address_complement: string | null
  district: string | null
  postal_code: string | null
  city: string | null
  state: string | null
  email: string | null
  phone: string | null
  mei: boolean
  simple_national: boolean
  source_updated_at: string | null
  looked_up_at: string
}

export interface CnpjRefreshPreview {
  current: CnpjPreview
  incoming: CnpjPreview
  changes: Record<string, { from: unknown, to: unknown }>
}

export interface ClientListParams {
  page: number
  per_page: number
  q?: string
  status?: ClientStatus
  tax_regime?: TaxRegime
  deadline_status?: DeadlineStatus
  sort: 'name' | 'tax_id' | 'status' | 'tax_regime' | 'created_at'
  direction: 'asc' | 'desc'
}

export interface ClientWritePayload {
  person_type: ClientPersonType
  tax_id: string
  name?: string
  status: ClientStatus
  tax_regime: TaxRegime
  email?: string
  phone?: string
  street_type?: string
  street?: string
  address_number?: string
  address_complement?: string
  district?: string
  postal_code?: string
  city?: string
  state?: string
}

export type ClientUpdatePayload = Partial<Omit<ClientWritePayload, 'person_type' | 'tax_id'>>

export interface PowerOfAttorneyPayload {
  starts_at: string
  expires_at: string
  notes?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  links: Record<string, string | null>
  meta: { current_page: number, last_page: number, per_page: number, total: number }
}
