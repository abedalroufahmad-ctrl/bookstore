import { createContext, useContext } from 'react'

export type WeightUnit = 'kg' | 'g' | 'lb' | 'oz'

export type CatalogKind = 'books' | 'authors' | 'categories' | 'warehouses'

export interface CatalogLayout {
  per_page: number
  per_row: number
}

export interface Settings {
  paypal_mode: 'sandbox' | 'live' | null
  global_discount: number
  invoice_shipping_fee: number
  weight_unit: WeightUnit
  catalog_items_per_page: number
  books: CatalogLayout
  authors: CatalogLayout
  categories: CatalogLayout
  warehouses: CatalogLayout
}

export interface SettingsContextType {
  settings: Settings
  isLoading: boolean
}

export const SettingsContext = createContext<SettingsContextType | undefined>(undefined)

export function useSettings() {
  const context = useContext(SettingsContext)
  if (context === undefined) {
    throw new Error('useSettings must be used within a SettingsProvider')
  }
  return context
}
