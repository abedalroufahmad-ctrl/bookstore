import React, { createContext, useContext } from 'react'
import { useQuery } from '@tanstack/react-query'
import { settings as settingsApi } from '../lib/api'

export type WeightUnit = 'kg' | 'g' | 'lb' | 'oz'

interface Settings {
    global_discount: number
    weight_unit: WeightUnit
    catalog_items_per_page: number
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

    const raw = data?.data
    const settings: Settings = {
        global_discount: typeof raw?.global_discount === 'number' ? raw.global_discount : 0,
        weight_unit: (raw?.weight_unit as WeightUnit) || 'kg',
        catalog_items_per_page: typeof raw?.catalog_items_per_page === 'number' && raw.catalog_items_per_page >= 1
            ? Math.min(100, Math.round(raw.catalog_items_per_page))
            : 35,
    }

    return (
        <SettingsContext.Provider value={{ settings, isLoading }}>
            {children}
        </SettingsContext.Provider>
    )
}

export function useSettings() {
    const context = useContext(SettingsContext)
    if (context === undefined) {
        throw new Error('useSettings must be used within a SettingsProvider')
    }
    return context
}
