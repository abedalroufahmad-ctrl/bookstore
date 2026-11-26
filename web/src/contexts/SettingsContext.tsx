import React from 'react'
import { useQuery } from '@tanstack/react-query'
import { settings as settingsApi } from '../lib/api'
import { SettingsContext, type Settings, type WeightUnit } from './settingsContextCore'
import { parseCatalogLayout } from './settingsUtils'

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
