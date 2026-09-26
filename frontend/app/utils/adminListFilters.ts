export function adminListParams(
  page: number,
  search: string,
  filterKey: string,
  filterValue: string
): Record<string, string | number> {
  const params: Record<string, string | number> = { page }
  const query = search.trim()

  if (query) params.q = query
  if (filterValue !== 'all') params[filterKey] = filterValue

  return params
}

export function pageWithinLastPage(currentPage: number, lastPage: number): number {
  return Math.min(currentPage, Math.max(1, lastPage))
}

interface LatestRequestHandlers<T> {
  onSuccess: (value: T) => void
  onError: (error: unknown) => void
  onSettled: () => void
}

export function createLatestRequestRunner() {
  let latestRequest = 0

  return async function runLatest<T>(
    request: () => Promise<T>,
    handlers: LatestRequestHandlers<T>
  ): Promise<void> {
    const requestId = ++latestRequest

    try {
      const value = await request()
      if (requestId === latestRequest) handlers.onSuccess(value)
    } catch (error) {
      if (requestId === latestRequest) handlers.onError(error)
    } finally {
      if (requestId === latestRequest) handlers.onSettled()
    }
  }
}
