import { useCallback, useEffect, useState } from 'react'

const STORAGE_KEY = 'bookstore.currency'
const CHANGE_EVENT = 'bookstore:currency-change'
const DEFAULT_CURRENCY = 'USD'

function readCurrency(): string {
  const stored = localStorage.getItem(STORAGE_KEY)
  return stored && /^[A-Z]{3}$/.test(stored) ? stored : DEFAULT_CURRENCY
}

export function useCurrencyPreference() {
  const [currencyCode, setCurrencyCodeState] = useState(readCurrency)

  useEffect(() => {
    const syncCurrency = () => setCurrencyCodeState(readCurrency())
    window.addEventListener(CHANGE_EVENT, syncCurrency)
    window.addEventListener('storage', syncCurrency)
    return () => {
      window.removeEventListener(CHANGE_EVENT, syncCurrency)
      window.removeEventListener('storage', syncCurrency)
    }
  }, [])

  const setCurrencyCode = useCallback((code: string) => {
    const normalized = code.toUpperCase()
    if (!/^[A-Z]{3}$/.test(normalized)) {
      throw new Error('Currency code must be a three-letter ISO code.')
    }
    localStorage.setItem(STORAGE_KEY, normalized)
    window.dispatchEvent(new Event(CHANGE_EVENT))
  }, [])

  return { currencyCode, setCurrencyCode }
}
