import React, { createContext, useContext } from 'react'
import { useQuery } from '@tanstack/react-query'
import { settings as settingsApi } from '../lib/api'

export type WeightUnit = 'kg' | 'g' | 'lb' | 'oz'

export type CatalogKind = 'books' | 'authors' | 'categories' | 'warehouses'

export interface CatalogLayout {
    per_page: number
    per_row: number
}

interface Settings {
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

interface SettingsContextType {
    settings: Settings
    isLoading: boolean
}

const SettingsContext = createContext<SettingsContextType | undefined>(undefined)

/** Weight is stored in grams (g). When you get it, it remains as-is. Conversion from grams to display unit only. */
export const GRAMS_TO_UNIT: Record<WeightUnit, number> = {
    g: 1,
    kg: 0.001,
    lb: 1 / 453.592,
    oz: 1 / 28.3495,
}

/** Format weight (stored in grams) for display in the configured unit. Value remains in g in data; we only convert for display. */
export function formatWeight(weightG: number | undefined | null, unit: WeightUnit): string {
    if (weightG == null) return ''
    const value = weightG * GRAMS_TO_UNIT[unit]
    const decimals = value >= 100 ? 0 : value >= 10 ? 1 : value >= 1 ? 2 : 3
    return `${value.toFixed(decimals)} ${unit}`
}

/** Convert display value (in given unit) to grams for storage. Stored value stays in g. */
export function displayToGrams(value: number | undefined | null, unit: WeightUnit): number | undefined {
    if (value == null || value === 0 || Number.isNaN(value)) return undefined
    return value / GRAMS_TO_UNIT[unit]
}

/** Convert stored weight (grams) to display value for input in the given unit */
export function gramsToDisplay(weightG: number | undefined | null, unit: WeightUnit): number | '' {
    if (weightG == null) return ''
    return weightG * GRAMS_TO_UNIT[unit]
}

/** @deprecated Use displayToGrams. Kept for compatibility. */
export function displayToKg(value: number | undefined | null, unit: WeightUnit): number | undefined {
    return displayToGrams(value, unit)
}

/** @deprecated Use gramsToDisplay. Kept for compatibility. */
export function kgToDisplay(weightKg: number | undefined | null, unit: WeightUnit): number | '' {
    return gramsToDisplay(weightKg, unit)
}

export function SettingsProvider({ children }: { children: React.ReactNode }) {
    const { data, isLoading } = useQuery({
        queryKey: ['settings'],
        queryFn: async () => {
            const res = await settingsApi.get()
            return res.data
        },
        staleTime: 1000 * 60 * 5,
    })

    const raw = data?.data as Record<string, unknown> | undefined
    const books = parseCatalogLayout(raw, 'books', 27, 5)
    const settings: Settings = {
        paypal_mode: raw?.paypal_mode === 'sandbox' || raw?.paypal_mode === 'live'
            ? raw.paypal_mode
            : null,
        global_discount: typeof raw?.global_discount === 'number' ? raw.global_discount : 0,
        invoice_shipping_fee: typeof raw?.invoice_shipping_fee === 'number' ? Math.max(0, raw.invoice_shipping_fee) : 0,
        weight_unit: (raw?.weight_unit as WeightUnit) || 'kg',
        catalog_items_per_page: books.per_page,
        books,
        authors: parseCatalogLayout(raw, 'authors', 28, 4),
        categories: parseCatalogLayout(raw, 'categories', 28, 3),
        warehouses: parseCatalogLayout(raw, 'warehouses', 28, 3),
    }

    return (
        <SettingsContext.Provider value={{ settings, isLoading }}>
            {children}
        </SettingsContext.Provider>
    )
}

function clampInt(value: unknown, fallback: number, min: number, max: number): number {
    const n = typeof value === 'number' ? value : Number(value)
    if (!Number.isFinite(n)) return fallback
    return Math.min(max, Math.max(min, Math.round(n)))
}

function parseCatalogLayout(
    raw: Record<string, unknown> | undefined,
    kind: CatalogKind,
    defaultPage: number,
    defaultRow: number
): CatalogLayout {
    let perPage: unknown
    let perRow: unknown
    if (kind === 'books') {
        perPage = raw?.books_per_page ?? raw?.catalog_items_per_page
        perRow = raw?.books_per_row
    } else if (kind === 'authors') {
        perPage = raw?.authors_per_page
        perRow = raw?.authors_per_row
    } else if (kind === 'categories') {
        perPage = raw?.categories_per_page
        perRow = raw?.categories_per_row
    } else {
        perPage = raw?.warehouses_per_page
        perRow = raw?.warehouses_per_row
    }
    return {
        per_page: clampInt(perPage, defaultPage, 1, 100),
        per_row: clampInt(perRow, defaultRow, 1, 8),
    }
}

/** CSS custom property used by `.catalog-grid` / `.books-grid`. */
export function catalogGridStyle(perRow: number): React.CSSProperties {
    return { ['--catalog-cols' as string]: String(clampInt(perRow, 3, 1, 8)) }
}

export function useSettings() {
    const context = useContext(SettingsContext)
    if (context === undefined) {
        throw new Error('useSettings must be used within a SettingsProvider')
    }
    return context
}
