export function randomInt(min: number, max: number): number {
  return Math.floor(Math.random() * (max - min + 1)) + min
}

export function randomFrom<T>(array: T[]): T {
  return array[Math.floor(Math.random() * array.length)]!
}

export function formatTaxId(value: string | null): string {
  if (!value) return 'Não informado'
  if (value.length === 11) return value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
  return value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5')
}

export function maskTaxId(value: string | null): string {
  const formatted = formatTaxId(value)
  return value?.length === 11 ? `***.${value.slice(3, 6)}.${value.slice(6, 9)}-**` : formatted
}

export function formatDate(value: string | null): string {
  return value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeZone: 'UTC' }).format(new Date(value)) : 'Não informado'
}
