import type { CSSProperties } from 'react'
import type { CatalogKind, CatalogLayout, WeightUnit } from './settingsContextCore'

export const GRAMS_TO_UNIT: Record<WeightUnit, number> = {
  g: 1,
  kg: 0.001,
  lb: 1 / 453.592,
  oz: 1 / 28.3495,
}

export function formatWeight(weightG: number | undefined | null, unit: WeightUnit): string {
  if (weightG == null) return ''
  const value = weightG * GRAMS_TO_UNIT[unit]
  const decimals = value >= 100 ? 0 : value >= 10 ? 1 : value >= 1 ? 2 : 3
  return `${value.toFixed(decimals)} ${unit}`
}

export function displayToGrams(value: number | undefined | null, unit: WeightUnit): number | undefined {
  if (value == null || value === 0 || Number.isNaN(value)) return undefined
  return value / GRAMS_TO_UNIT[unit]
}

export function gramsToDisplay(weightG: number | undefined | null, unit: WeightUnit): number | '' {
  if (weightG == null) return ''
  return weightG * GRAMS_TO_UNIT[unit]
}

export function displayToKg(value: number | undefined | null, unit: WeightUnit): number | undefined {
  return displayToGrams(value, unit)
}

export function kgToDisplay(weightKg: number | undefined | null, unit: WeightUnit): number | '' {
  return gramsToDisplay(weightKg, unit)
}

function clampInt(value: unknown, fallback: number, min: number, max: number): number {
  const n = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(n)) return fallback
  return Math.min(max, Math.max(min, Math.round(n)))
}

export function parseCatalogLayout(
  raw: Record<string, unknown> | undefined,
  kind: CatalogKind,
  defaultPage: number,
  defaultRow: number,
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

export function catalogGridStyle(perRow: number): CSSProperties {
  return { ['--catalog-cols' as string]: String(clampInt(perRow, 3, 1, 8)) }
}
