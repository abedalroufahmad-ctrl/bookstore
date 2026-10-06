import { useTranslation } from 'react-i18next'

export type BookConditionFilterValue = '' | 'new' | 'used'

type Props = {
  value: BookConditionFilterValue
  onChange: (value: BookConditionFilterValue) => void
  className?: string
}

export function BookConditionFilter({ value, onChange, className = '' }: Props) {
  const { t } = useTranslation()
  const options: { value: BookConditionFilterValue; label: string }[] = [
    { value: '', label: t('books.filterAll') },
    { value: 'new', label: t('bookDetail.conditionNew') },
    { value: 'used', label: t('bookDetail.conditionUsed') },
  ]

  return (
    <div className={`flex flex-wrap items-center gap-2 ${className}`}>
      <span className="text-sm font-medium" style={{ color: 'var(--color-text-muted)' }}>
        {t('books.conditionFilter')}
      </span>
      <div className="inline-flex rounded-lg border overflow-hidden" style={{ borderColor: 'var(--color-border)' }}>
        {options.map((opt) => {
          const active = value === opt.value
          return (
            <button
              key={opt.value || 'all'}
              type="button"
              onClick={() => onChange(opt.value)}
              className="px-3 py-1.5 text-sm font-medium transition-colors"
              style={{
                background: active ? 'var(--color-primary)' : 'transparent',
                color: active ? '#fff' : 'var(--color-text)',
              }}
            >
              {opt.label}
            </button>
          )
        })}
      </div>
    </div>
  )
}
