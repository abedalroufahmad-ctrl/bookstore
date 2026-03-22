import { useTranslation } from 'react-i18next'

type AdminListSearchBarProps = {
  value: string
  onChange: (next: string) => void
  placeholder: string
  /** Shown under the input (e.g. auto-search explanation) */
  hint?: string
  /** Overrides default aria-label (defaults to placeholder) */
  ariaLabel?: string
  isFetching: boolean
  /** Last applied search (after Space / Enter / blur) — not updated on every keystroke */
  committedValue: string
  /** Apply current input as the search query (wired to Space, Enter, blur) */
  onCommit: (value: string) => void
  className?: string
}

export function AdminListSearchBar({
  value,
  onChange,
  placeholder,
  hint,
  ariaLabel,
  isFetching,
  committedValue,
  onCommit,
  className = '',
}: AdminListSearchBarProps) {
  const { t } = useTranslation()
  const pending = value.trim() !== committedValue.trim()
  const showIndicator = isFetching || pending

  const handleChange = (next: string) => {
    onChange(next)
    if (next.endsWith(' ')) {
      window.setTimeout(() => onCommit(next), 0)
    }
  }

  return (
    <div className={className}>
      <div className="relative w-full max-w-md">
        <input
          type="search"
          value={value}
          onChange={(e) => handleChange(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              onCommit(value)
            }
          }}
          placeholder={placeholder}
          autoComplete="off"
          aria-label={ariaLabel ?? placeholder}
          className="w-full pl-4 pr-24 py-2.5 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-200 focus:border-amber-400 outline-none"
        />
        {showIndicator && (
          <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-amber-800 pointer-events-none">
            {t('admin.searching')}
          </span>
        )}
      </div>
      {hint ? <p className="text-sm text-stone-500 mt-2">{hint}</p> : null}
    </div>
  )
}
