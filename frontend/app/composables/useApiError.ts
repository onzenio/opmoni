function asRecord(value: unknown): Record<string, unknown> | null {
  return typeof value === 'object' && value !== null ? (value as Record<string, unknown>) : null
}

export function apiMessage(e: unknown): string | undefined {
  const record = asRecord(e)
  if (!record) return undefined
  const data = asRecord(record.data) ?? asRecord(record.response)
  const nested = asRecord(data?._data) ?? data
  const resolved = nested?.message ?? record.message
  return typeof resolved === 'string' && resolved.length > 0 ? resolved : undefined
}

export function apiStatus(e: unknown): number | null {
  const record = asRecord(e)
  const data = asRecord(record?.data)
  const response = asRecord(record?.response)
  const status = record?.statusCode ?? record?.status ?? response?.status ?? data?.status
  return typeof status === 'number' ? status : null
}

export function apiFieldErrors(e: unknown): Record<string, string> {
  const data = asRecord(asRecord(e)?.data)
  const errors = asRecord(data?.errors)
  if (!errors) return {}
  return Object.fromEntries(
    Object.entries(errors)
      .map(([key, value]) => [key, Array.isArray(value) ? value[0] : value])
      .filter((entry): entry is [string, string] => typeof entry[1] === 'string')
  )
}
