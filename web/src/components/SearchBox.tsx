import { useState, useEffect, useCallback } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'

type SearchType = 'books' | 'authors' | 'categories'

interface SearchBoxProps {
  variant?: 'nav' | 'home'
  className?: string
  homePlaceholder?: string
}

export function SearchBox({ variant = 'nav', className = '', homePlaceholder }: SearchBoxProps) {
  const { t } = useTranslation()
  const location = useLocation()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const urlSearch = searchParams.get('search') ?? ''
  const [localSearch, setLocalSearch] = useState(urlSearch)
  const [searchType, setSearchType] = useState<SearchType>('books')
  const [isFocused, setIsFocused] = useState(false)

  useEffect(() => {
    setLocalSearch(urlSearch)
  }, [urlSearch])

  const path = location.pathname
  const isBooksListPage = path === '/books'
  const isAuthorsListPage = path === '/authors'
  const isCategoriesListPage = path === '/categories'
  const isAuthorDetailPage = path.match(/^\/authors\/[^/]+$/)
  const isCategoryDetailPage = path.match(/^\/categories\/[^/]+$/)
  const isCatalogListPage = isBooksListPage || isAuthorsListPage || isCategoriesListPage
  const isCatalogDetailPage = isAuthorDetailPage || isCategoryDetailPage
  const isCatalogPage = isCatalogListPage || isCatalogDetailPage

  const applySearch = useCallback(
    (value: string, type?: SearchType) => {
      const trimmed = value.trim()
      const routes: Record<SearchType, string> = {
        books: '/books',
        authors: '/authors',
        categories: '/categories',
      }
      if (variant === 'home' && type) {
        navigate(trimmed ? `${routes[type]}?search=${encodeURIComponent(trimmed)}` : routes[type])
      } else if (isCatalogPage) {
        const params = new URLSearchParams(searchParams)
        if (trimmed) {
          params.set('search', trimmed)
          params.set('page', '1')
        } else {
          params.delete('search')
          params.delete('page')
        }
        setSearchParams(params, { replace: true })
      } else {
        const targetType = isBooksListPage
          ? 'books'
          : isAuthorsListPage
            ? 'authors'
            : isCategoriesListPage
              ? 'categories'
              : 'books'
        navigate(trimmed ? `${routes[targetType]}?search=${encodeURIComponent(trimmed)}` : routes[targetType])
      }
    },
    [variant, isCatalogPage, isBooksListPage, isAuthorsListPage, isCategoriesListPage, navigate, searchParams, setSearchParams],
  )

  /** Catalog nav: update URL only after space / Enter / submit — not on every keystroke */
  const applyCatalogNavIfFocused = useCallback(
    (raw: string) => {
      const trimmed = raw.trim()
      if (variant === 'nav' && isCatalogPage && isFocused && trimmed !== urlSearch) {
        const params = new URLSearchParams(searchParams)
        if (trimmed) {
          params.set('search', trimmed)
          params.set('page', '1')
        } else {
          params.delete('search')
          params.delete('page')
        }
        setSearchParams(params, { replace: true })
      }
    },
    [variant, isCatalogPage, isFocused, urlSearch, searchParams, setSearchParams],
  )

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    applySearch(localSearch, variant === 'home' ? searchType : undefined)
  }

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const next = e.target.value
    setLocalSearch(next)
    if (next.endsWith(' ')) {
      window.setTimeout(() => applyCatalogNavIfFocused(next), 0)
    }
  }

  const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      applyCatalogNavIfFocused(e.currentTarget.value)
    }
  }

  if (variant === 'home') {
    return (
      <form onSubmit={handleSubmit} className={`flex gap-2 ${className}`}>
        <select
          value={searchType}
          onChange={(e) => setSearchType(e.target.value as SearchType)}
          className="search-input px-3 py-2.5 bg-white min-w-[120px]"
          style={{ borderColor: 'var(--color-border)' }}
        >
          <option value="books">{t('nav.books')}</option>
          <option value="authors">{t('nav.authors')}</option>
          <option value="categories">{t('nav.categories')}</option>
        </select>
        <input
          type="search"
          value={localSearch}
          onChange={handleChange}
          onKeyDown={handleSearchKeyDown}
          onFocus={() => setIsFocused(true)}
          onBlur={() => setIsFocused(false)}
          placeholder={variant === 'home' && homePlaceholder ? homePlaceholder : t('search.placeholder')}
          className="search-input flex-1 min-w-[200px] px-4 py-2.5 bg-white"
          style={{ borderColor: 'var(--color-border)' }}
        />
        <button
          type="submit"
          className="px-6 py-2.5 rounded-lg font-medium text-white transition-colors hover:opacity-90"
          style={{ background: 'var(--color-primary)' }}
        >
          {t('search.search')}
        </button>
      </form>
    )
  }

  return (
    <form onSubmit={handleSubmit} className={`flex-1 max-w-xs ${className}`}>
      <input
        type="search"
        value={localSearch}
        onChange={handleChange}
        onKeyDown={handleSearchKeyDown}
        onFocus={() => setIsFocused(true)}
        onBlur={() => setIsFocused(false)}
        placeholder={t('search.placeholder')}
        className="search-input w-full px-3 py-2 bg-white"
        style={{ borderColor: 'var(--color-border)' }}
      />
    </form>
  )
}
