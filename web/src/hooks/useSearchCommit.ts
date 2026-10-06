import { useCallback, useState } from 'react'

/**
 * Search field: typing updates `searchInput` only.
 * Call `commitSearch(raw)` (from Space / Enter / blur in the search bar) to update `committedSearch` for API/query.
 */
export function useSearchCommit() {
  const [searchInput, setSearchInput] = useState('')
  const [committedSearch, setCommittedSearch] = useState('')

  const commitSearch = useCallback((raw: string) => {
    setCommittedSearch(raw.trim())
  }, [])

  return { searchInput, setSearchInput, committedSearch, commitSearch }
}
