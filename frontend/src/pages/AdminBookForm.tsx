import { useState, useEffect } from 'react'
import { useSearchCommit } from '../hooks/useSearchCommit'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueries, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { admin, type Book, type BookFormData } from '../lib/api'
import { resolveCoverUrl } from '../lib/utils'
import { useSettings, gramsToDisplay, displayToGrams } from '../contexts/SettingsContext'

const emptyForm: BookFormData = {
  title: '',
  author_ids: [],
  category_id: '',
  warehouse_id: '',
  price: 0,
  isbn: '',
  stock_quantity: 0,
  description: '',
  pages: undefined,
  publish_year: undefined,
  publisher: '',
  size: '',
  weight: undefined,
  cover_image: '',
  cover_image_thumb: '',
  edition_number: undefined,
  discount_percent: 0,
}

function extractList<T>(data: unknown): T[] {
  if (!data) return []
  const d = data as Record<string, unknown>
  if (Array.isArray(d.data)) return d.data as T[]
  if (d.data && typeof d.data === 'object' && 'data' in d.data) {
    return (d.data as { data: T[] }).data
  }
  return Array.isArray(d) ? d : []
}

/** Normalize ID from API (may be _id, id, or { $oid: string }) */
function normalizeId(value: unknown): string | null {
  if (typeof value === 'string') return value
  if (value && typeof value === 'object' && '$oid' in value) return (value as { $oid: string }).$oid
  if (value && typeof value === 'object' && 'id' in value) return String((value as { id: string }).id)
  return null
}

export function AdminBookForm() {
    const { t } = useTranslation()
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const { settings } = useSettings()
    const isEdit = Boolean(id)

  const [form, setForm] = useState<BookFormData>(emptyForm)
  const [error, setError] = useState('')
  const {
    searchInput: authorSearch,
    setSearchInput: setAuthorSearch,
    committedSearch: authorSearchDebounced,
    commitSearch: commitAuthorSearch,
  } = useSearchCommit()
  const [authorDropdownOpen, setAuthorDropdownOpen] = useState(false)

  const [, setNewAuthorName] = useState('')
  const {
    searchInput: categorySearch,
    setSearchInput: setCategorySearch,
    committedSearch: categorySearchDebounced,
    commitSearch: commitCategorySearch,
  } = useSearchCommit()
  const [categoryDropdownOpen, setCategoryDropdownOpen] = useState(false)
  const [newCategory, setNewCategory] = useState({ dewey_code: '', subject_title: '' })
  const [addingAuthor, setAddingAuthor] = useState(false)
  const [addingCategory, setAddingCategory] = useState(false)
  const [coverUploading, setCoverUploading] = useState(false)

  const { data: bookData } = useQuery({
    queryKey: ['admin-book', id],
    queryFn: async () => {
      const res = await admin.books.get(id!)
      return res.data
    },
    enabled: isEdit,
  })

  const { data: authorsData } = useQuery({
    queryKey: ['admin-authors'],
    queryFn: async () => {
      const res = await admin.authors.list({ per_page: 100 })
      return res.data
    },
  })

  const { data: authorsSearchData, isFetching: authorsSearchLoading } = useQuery({
    queryKey: ['admin-authors-search', authorSearchDebounced],
    queryFn: async () => {
      const params = authorSearchDebounced
        ? { search: authorSearchDebounced, per_page: 50 }
        : { per_page: 50 }
      const res = await admin.authors.list(params)
      return res.data
    },
    enabled: authorDropdownOpen,
  })

  const { data: categoriesData } = useQuery({
    queryKey: ['admin-categories'],
    queryFn: async () => {
      const res = await admin.categories.list({ per_page: 100 })
      return res.data
    },
  })

  const { data: categoriesSearchData, isFetching: categoriesSearchLoading } = useQuery({
    queryKey: ['admin-categories-search', categorySearchDebounced],
    queryFn: async () => {
      const params = categorySearchDebounced
        ? { search: categorySearchDebounced, per_page: 50 }
        : { per_page: 50 }
      const res = await admin.categories.list(params)
      return res.data
    },
    enabled: categoryDropdownOpen,
  })

  const { data: warehousesData } = useQuery({
    queryKey: ['admin-warehouses'],
    queryFn: async () => {
      const res = await admin.warehouses.list({ per_page: 100 })
      return res.data
    },
  })

  const authorList = extractList<{ _id: string; name: string }>(authorsData)
  const searchAuthorList = extractList<{ _id: string; name: string }>(authorsSearchData)
  const rawBook = bookData?.data as (Book & { authors?: Array<Record<string, unknown>> }) | undefined
  const bookAuthors: { _id: string; name: string }[] = (rawBook?.authors ?? []).map((a) => {
    const id = normalizeId(a._id ?? a.id) ?? ''
    const name = (a.name ?? a.title) as string | undefined
    return { _id: id, name: name ?? '' }
  }).filter((a) => a._id)

  const authorIdsNeedingFetch = form.author_ids.filter(
    (authorId) =>
      !authorList.some((a) => a._id === authorId) &&
      !bookAuthors.some((a) => a._id === authorId && a.name)
  )
  const fetchedAuthors = useQueries({
    queries: authorIdsNeedingFetch.map((authorId) => ({
      queryKey: ['admin-author', authorId],
      queryFn: async () => {
        const res = await admin.authors.get(authorId)
        const data = (res.data as { data?: { _id?: string; id?: string; name?: string } })?.data
        const id = normalizeId(data?._id ?? data?.id) ?? authorId
        const name = data?.name ?? ''
        return { _id: id, name }
      },
      enabled: Boolean(authorId),
    })),
  })
  const fetchedAuthorMap = Object.fromEntries(
    fetchedAuthors
      .filter((r) => r.data)
      .map((r) => [r.data!._id, r.data!.name])
  )

  const categoryList = extractList<{
    _id: string
    subject_title: string
    dewey_code: string
  }>(categoriesData)
  const searchCategoryList = extractList<{
    _id: string
    subject_title: string
    dewey_code: string
  }>(categoriesSearchData)
  const rawBookCategory = rawBook?.category as { _id?: string; id?: string; subject_title?: string; dewey_code?: string } | undefined
  const selectedCategoryLabel =
    (form.category_id && (() => {
      const fromList = categoryList.find((c) => c._id === form.category_id)
      if (fromList) return `${fromList.subject_title} (${fromList.dewey_code})`
      const fromSearch = searchCategoryList.find((c) => c._id === form.category_id)
      if (fromSearch) return `${fromSearch.subject_title} (${fromSearch.dewey_code})`
      if (rawBookCategory && normalizeId(rawBookCategory._id ?? rawBookCategory.id) === form.category_id) {
        return `${rawBookCategory.subject_title ?? ''} (${rawBookCategory.dewey_code ?? ''})`
      }
      return null
    })()) ?? null
  const categorySearchLower = categorySearch.trim().toLowerCase()
  const filteredCategories = searchCategoryList.filter(
    (c) => c._id !== form.category_id
  )
  const exactMatchCategory = searchCategoryList.some(
    (c) =>
      c.subject_title.toLowerCase() === categorySearchLower ||
      c.dewey_code.toLowerCase() === categorySearchLower
  )
  const showCreateCategory =
    categorySearch.trim().length > 0 && !exactMatchCategory && !addingCategory
  const warehouseList = extractList<{ _id: string; name: string }>(warehousesData)

  useEffect(() => {
    if (bookData?.data) {
      const b = bookData.data as Book & { authors?: Array<{ _id?: string; id?: string; name?: string }> }
      const rawAuthorIds = b.author_ids ?? b.authors?.map((a) => a._id ?? a.id) ?? []
      const authorIds = (Array.isArray(rawAuthorIds) ? rawAuthorIds : []).map((id) => normalizeId(id)).filter((id): id is string => id != null)
      setForm({
        title: b.title ?? '',
        author_ids: authorIds,
        category_id: normalizeId(b.category_id) ?? (b.category as { _id?: string } | undefined)?._id ?? '',
        warehouse_id: normalizeId(b.warehouse_id) ?? (b.warehouse as { _id?: string } | undefined)?._id ?? '',
        price: b.price ?? 0,
        isbn: b.isbn ?? '',
        stock_quantity: b.stock_quantity ?? 0,
        description: b.description ?? '',
        pages: b.pages,
        publish_year: b.publish_year,
        publisher: b.publisher ?? '',
        size: b.size ?? '',
        weight: b.weight,
        cover_image: b.cover_image ?? '',
        cover_image_thumb: b.cover_image_thumb ?? '',
        edition_number: b.edition_number,
        discount_percent: b.discount_percent ?? 0,
      })
    }
  }, [bookData])

  const createMutation = useMutation({
    mutationFn: (data: BookFormData) => admin.books.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      navigate('/admin/books')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedCreate'))
    },
  })

  const updateMutation = useMutation({
    mutationFn: (data: Partial<BookFormData>) =>
      admin.books.update(id!, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-books'] })
      navigate('/admin/books')
    },
    onError: (err: { response?: { data?: { message?: string } } }) => {
      setError(err?.response?.data?.message ?? t('admin.failedUpdate'))
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    if (!form.category_id) {
      setError(t('admin.selectCategory'))
      return
    }
    const payload: BookFormData = {
      ...form,
      author_ids: form.author_ids.length ? form.author_ids : [(authorList[0]?._id) ?? ''],
      category_id: form.category_id,
      warehouse_id: (form.warehouse_id || warehouseList[0]?._id) ?? '',
    }
    if (isEdit) {
      updateMutation.mutate(payload)
    } else {
      if (!payload.author_ids.length || !payload.warehouse_id) {
        setError(t('admin.addFirst'))
        return
      }
      createMutation.mutate(payload)
    }
  }

  const selectedAuthors = form.author_ids
    .map((authorId) => {
      const fromList = authorList.find((a) => a._id === authorId)
      if (fromList) return fromList
      const fromSearch = searchAuthorList.find((a) => a._id === authorId)
      if (fromSearch) return fromSearch
      const fromBook = bookAuthors.find((a) => a._id === authorId)
      if (fromBook && fromBook.name) return { _id: fromBook._id, name: fromBook.name }
      const fetchedName = fetchedAuthorMap[authorId]
      if (fetchedName) return { _id: authorId, name: fetchedName }
      return { _id: authorId, name: '' }
    })
    .filter((a): a is { _id: string; name: string } => Boolean(a._id))
  const authorSearchLower = authorSearch.trim().toLowerCase()
  const filteredAuthors = searchAuthorList.filter(
    (a) => !form.author_ids.includes(a._id)
  )
  const exactMatch = searchAuthorList.some(
    (a) => a.name.toLowerCase() === authorSearchLower
  )
  const showCreateAuthor =
    authorSearch.trim().length > 0 && !exactMatch && !addingAuthor

  const addAuthorToBook = (authorId: string) => {
    if (form.author_ids.includes(authorId)) return
    setForm((prev) => ({
      ...prev,
      author_ids: [...prev.author_ids, authorId],
    }))
    setAuthorSearch('')
    setAuthorDropdownOpen(false)
  }

  const removeAuthorFromBook = (authorId: string) => {
    setForm((prev) => ({
      ...prev,
      author_ids: prev.author_ids.filter((id) => id !== authorId),
    }))
  }

  const addAuthorMutation = useMutation({
    mutationFn: (name: string) => admin.authors.create({ name }),
    onSuccess: (axiosRes) => {
      const data = axiosRes.data?.data as { _id: string } | undefined
      if (data) {
        setForm((prev) => ({
          ...prev,
          author_ids: [...prev.author_ids, data._id],
        }))
        queryClient.invalidateQueries({ queryKey: ['admin-authors'] })
        queryClient.invalidateQueries({ queryKey: ['admin-authors-search'] })
        setNewAuthorName('')
        setAddingAuthor(false)
        setAuthorSearch('')
        setAuthorDropdownOpen(false)
      }
    },
  })

  const selectCategory = (categoryId: string) => {
    setForm((prev) => ({ ...prev, category_id: categoryId }))
    setCategorySearch('')
    setCategoryDropdownOpen(false)
  }

  const clearCategory = () => {
    setForm((prev) => ({ ...prev, category_id: '' }))
  }

  const addCategoryMutation = useMutation({
    mutationFn: (data: { dewey_code: string; subject_title: string }) =>
      admin.categories.create(data),
    onSuccess: (axiosRes) => {
      const data = axiosRes.data?.data as { _id: string } | undefined
      if (data) {
        setForm((prev) => ({ ...prev, category_id: data._id }))
        queryClient.invalidateQueries({ queryKey: ['admin-categories'] })
        queryClient.invalidateQueries({ queryKey: ['admin-categories-search'] })
        setNewCategory({ dewey_code: '', subject_title: '' })
        setAddingCategory(false)
        setCategorySearch('')
        setCategoryDropdownOpen(false)
      }
    },
  })

  const loading = createMutation.isPending || updateMutation.isPending

  const needsData = !isEdit && warehouseList.length === 0

  if (needsData) {
    return (
      <div>
        <h1 className="text-2xl font-bold text-amber-900 mb-6">
          {isEdit ? t('admin.editBook') : t('admin.addBook')}
        </h1>
        <p className="text-stone-600 mb-4">
          {t('admin.booksNeedsWarehouse')}
        </p>
        <button
          type="button"
          onClick={() => navigate('/admin/books')}
          className="px-6 py-2 border border-stone-300 rounded-lg hover:bg-stone-50"
        >
          {t('admin.backToBooks')}
        </button>
      </div>
    )
  }

  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-6">
        {isEdit ? t('admin.editBook') : t('admin.addBook')}
      </h1>
      <form onSubmit={handleSubmit} className="max-w-xl space-y-4">
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.title')} *
          </label>
          <input
            type="text"
            value={form.title}
            onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.isbn')} *
          </label>
          <input
            type="text"
            value={form.isbn}
            onChange={(e) => setForm((p) => ({ ...p, isbn: e.target.value }))}
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.price')} *
          </label>
          <input
            type="number"
            step="0.01"
            min="0"
            value={form.price || ''}
            onChange={(e) =>
              setForm((p) => ({ ...p, price: parseFloat(e.target.value) || 0 }))
            }
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.stockQuantity')} *
          </label>
          <input
            type="number"
            min="0"
            value={form.stock_quantity}
            onChange={(e) =>
              setForm((p) => ({
                ...p,
                stock_quantity: parseInt(e.target.value, 10) || 0,
              }))
            }
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.specialDiscount')}
          </label>
          <input
            type="number"
            step="0.1"
            min="0"
            max="100"
            value={form.discount_percent || ''}
            onChange={(e) =>
              setForm((p) => ({ ...p, discount_percent: parseFloat(e.target.value) || 0 }))
            }
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          />
          <p className="mt-1 text-xs text-stone-500">{t('admin.globalDiscountHint')}</p>
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.categories')} *
          </label>
          <div className="relative">
            <input
              type="text"
              value={categorySearch}
              onChange={(e) => {
                const next = e.target.value
                setCategorySearch(next)
                setCategoryDropdownOpen(true)
                if (next.endsWith(' ')) {
                  window.setTimeout(() => commitCategorySearch(next), 0)
                }
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault()
                  commitCategorySearch(e.currentTarget.value)
                }
              }}
              onFocus={() => setCategoryDropdownOpen(true)}
              onBlur={() => setTimeout(() => setCategoryDropdownOpen(false), 180)}
              placeholder={t('admin.searchCategory') ?? 'Search or add category...'}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
            {categoryDropdownOpen && (
              <ul
                className="absolute z-10 mt-1 w-full max-h-48 overflow-auto bg-white border border-stone-200 rounded-lg shadow-lg py-1"
                onMouseDown={(e) => e.preventDefault()}
              >
                {categoriesSearchLoading ? (
                  <li className="px-4 py-3 text-sm text-stone-500">
                    {t('common.loading')}
                  </li>
                ) : (
                  <>
                    {filteredCategories.slice(0, 25).map((c) => (
                      <li key={c._id}>
                        <button
                          type="button"
                          onClick={() => selectCategory(c._id)}
                          className="w-full px-4 py-2 text-left text-sm hover:bg-amber-50"
                        >
                          {c.subject_title} ({c.dewey_code})
                        </button>
                      </li>
                    ))}
                    {!categoriesSearchLoading && filteredCategories.length === 0 && !showCreateCategory && (
                      <li className="px-4 py-2 text-sm text-stone-500">
                        {categorySearchDebounced ? (t('admin.noCategoriesMatch') ?? 'No categories match.') : (t('admin.typeToSearchCategories') ?? 'Type to search all categories.')}
                      </li>
                    )}
                    {showCreateCategory && (
                      <li className="border-t border-stone-100">
                        <button
                          type="button"
                          onClick={() => {
                            setAddingCategory(true)
                            setNewCategory((p) => ({ ...p, subject_title: categorySearch.trim() }))
                            setCategoryDropdownOpen(false)
                          }}
                          className="w-full px-4 py-2 text-left text-sm text-amber-700 hover:bg-amber-50 font-medium"
                        >
                          {t('admin.createNewCategoryNamed', { name: categorySearch.trim() }) ??
                            `Add new category "${categorySearch.trim()}"`}
                        </button>
                      </li>
                    )}
                  </>
                )}
              </ul>
            )}
          </div>
          {addingCategory && (
            <div className="mt-2 flex gap-2 items-center flex-wrap">
              <input
                type="text"
                value={newCategory.dewey_code}
                onChange={(e) =>
                  setNewCategory((p) => ({ ...p, dewey_code: e.target.value }))
                }
                placeholder={t('admin.deweyCode')}
                className="px-3 py-1 border border-stone-300 rounded-lg text-sm w-24"
              />
              <input
                type="text"
                value={newCategory.subject_title}
                onChange={(e) =>
                  setNewCategory((p) => ({ ...p, subject_title: e.target.value }))
                }
                placeholder={t('admin.subjectTitle')}
                className="px-3 py-1 border border-stone-300 rounded-lg text-sm flex-1 min-w-[120px]"
              />
              <button
                type="button"
                onClick={() =>
                  newCategory.dewey_code &&
                  newCategory.subject_title &&
                  addCategoryMutation.mutate(newCategory)
                }
                disabled={
                  addCategoryMutation.isPending ||
                  !newCategory.dewey_code ||
                  !newCategory.subject_title
                }
                className="text-sm px-3 py-1 bg-amber-100 text-amber-900 rounded-lg hover:bg-amber-200"
              >
                {t('admin.add')}
              </button>
              <button
                type="button"
                onClick={() => {
                  setAddingCategory(false)
                  setNewCategory({ dewey_code: '', subject_title: '' })
                }}
                className="text-sm text-stone-500 hover:underline"
              >
                {t('admin.cancel')}
              </button>
            </div>
          )}
          <div className="flex flex-wrap gap-2 mt-2">
            {selectedCategoryLabel && (
              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 border border-amber-200 text-amber-900 text-sm">
                {selectedCategoryLabel}
                <button
                  type="button"
                  onClick={clearCategory}
                  className="text-amber-700 hover:text-amber-900 font-medium"
                  aria-label={t('admin.remove') ?? 'Remove'}
                >
                  ×
                </button>
              </span>
            )}
          </div>
          {!selectedCategoryLabel && (
            <p className="mt-1 text-xs text-stone-500">
              {t('admin.searchCategoryHint') ?? 'Type to search and select a category, or create a new one.'}
            </p>
          )}
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.warehouse')} *
          </label>
          <select
            value={form.warehouse_id}
            onChange={(e) =>
              setForm((p) => ({ ...p, warehouse_id: e.target.value }))
            }
            required
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          >
            <option value="">{t('admin.selectWarehouse')}</option>
            {warehouseList.map((w) => (
              <option key={w._id} value={w._id}>
                {w.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.authors')} *
          </label>
          <div className="relative">
            <input
              type="text"
              value={authorSearch}
              onChange={(e) => {
                const next = e.target.value
                setAuthorSearch(next)
                setAuthorDropdownOpen(true)
                if (next.endsWith(' ')) {
                  window.setTimeout(() => commitAuthorSearch(next), 0)
                }
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault()
                  commitAuthorSearch(e.currentTarget.value)
                }
              }}
              onFocus={() => setAuthorDropdownOpen(true)}
              onBlur={() =>
                setTimeout(() => setAuthorDropdownOpen(false), 180)
              }
              placeholder={t('admin.searchAuthor') ?? 'Search or add author...'}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
            {authorDropdownOpen && (
              <ul
                className="absolute z-10 mt-1 w-full max-h-48 overflow-auto bg-white border border-stone-200 rounded-lg shadow-lg py-1"
                onMouseDown={(e) => e.preventDefault()}
              >
                {authorsSearchLoading ? (
                  <li className="px-4 py-3 text-sm text-stone-500">
                    {t('common.loading')}
                  </li>
                ) : (
                  <>
                    {filteredAuthors.slice(0, 25).map((a) => (
                      <li key={a._id}>
                        <button
                          type="button"
                          onClick={() => addAuthorToBook(a._id)}
                          className="w-full px-4 py-2 text-left text-sm hover:bg-amber-50"
                        >
                          {a.name}
                        </button>
                      </li>
                    ))}
                    {!authorsSearchLoading && filteredAuthors.length === 0 && !showCreateAuthor && (
                      <li className="px-4 py-2 text-sm text-stone-500">
                        {authorSearchDebounced ? (t('admin.noAuthorsMatch') ?? 'No authors match.') : (t('admin.typeToSearchAuthors') ?? 'Type to search all authors.')}
                      </li>
                    )}
                    {showCreateAuthor && (
                      <li className="border-t border-stone-100">
                        <button
                          type="button"
                          onClick={() =>
                            addAuthorMutation.mutate(authorSearch.trim())
                          }
                          disabled={addAuthorMutation.isPending}
                          className="w-full px-4 py-2 text-left text-sm text-amber-700 hover:bg-amber-50 font-medium"
                        >
                          {addAuthorMutation.isPending
                            ? t('common.loading')
                            : (t('admin.createNewAuthorNamed', { name: authorSearch.trim() }) ??
                              `Add new author "${authorSearch.trim()}"`)}
                        </button>
                      </li>
                    )}
                  </>
                )}
              </ul>
            )}
          </div>
          <div className="flex flex-wrap gap-2 mt-2">
            {selectedAuthors.map((a) => (
              <span
                key={a._id}
                className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 border border-amber-200 text-amber-900 text-sm"
              >
                {a.name || t('admin.authorName')}
                <button
                  type="button"
                  onClick={() => removeAuthorFromBook(a._id)}
                  className="text-amber-700 hover:text-amber-900 font-medium"
                  aria-label={t('admin.remove') ?? 'Remove'}
                >
                  ×
                </button>
              </span>
            ))}
          </div>
          {selectedAuthors.length === 0 && (
            <p className="mt-1 text-xs text-stone-500">
              {t('admin.searchAuthorHint') ?? 'Type to search and select authors, or create a new one.'}
            </p>
          )}
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.description')}
          </label>
          <textarea
            value={form.description || ''}
            onChange={(e) =>
              setForm((p) => ({ ...p, description: e.target.value }))
            }
            rows={3}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-stone-700 mb-1">
              {t('admin.size')}
            </label>
            <input
              type="text"
              value={form.size ?? ''}
              onChange={(e) => setForm((p) => ({ ...p, size: e.target.value }))}
              placeholder={t('common.sizePlaceholder')}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-stone-700 mb-1">
              {t('admin.weightWithUnit', { unit: settings.weight_unit }) || `Weight (${settings.weight_unit})`}
            </label>
            <input
              type="number"
              step="any"
              min="0"
              value={gramsToDisplay(form.weight, settings.weight_unit)}
              onChange={(e) =>
                setForm((p) => ({
                  ...p,
                  weight: displayToGrams(e.target.value ? parseFloat(e.target.value) : undefined, settings.weight_unit),
                }))
              }
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.coverImage')}
          </label>
          <p className="text-xs text-stone-500 mb-2">
            {t('admin.coverImageHint')}
          </p>
          <input
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
            onChange={async (e) => {
              const file = e.target.files?.[0]
              if (!file) return
              setCoverUploading(true)
              try {
                const res = await admin.uploadCover(file)
                const data = res.data?.data
                if (data?.cover_image && data?.cover_image_thumb) {
                  setForm((p) => ({
                    ...p,
                    cover_image: data.cover_image,
                    cover_image_thumb: data.cover_image_thumb,
                  }))
                }
              } catch {
                setError(t('admin.failedUpload'))
              } finally {
                setCoverUploading(false)
                e.target.value = ''
              }
            }}
            disabled={coverUploading}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-100 file:text-amber-900 file:font-medium hover:file:bg-amber-200"
          />
          {coverUploading && (
            <p className="mt-1 text-sm text-amber-700">{t('admin.uploading')}</p>
          )}
          {(form.cover_image || form.cover_image_thumb) && (
            <div className="mt-3 flex gap-4 items-start">
              {form.cover_image_thumb && (
                <div>
                  <p className="text-xs text-stone-500 mb-1">{t('admin.thumbnail')}</p>
                  <img
                    src={resolveCoverUrl(form.cover_image_thumb)}
                    alt="Thumb"
                    className="h-24 w-auto rounded border border-stone-200"
                  />
                </div>
              )}
              {form.cover_image && (
                <div>
                  <p className="text-xs text-stone-500 mb-1">{t('admin.original')}</p>
                  <img
                    src={resolveCoverUrl(form.cover_image)}
                    alt="Cover"
                    className="max-h-24 w-auto rounded border border-stone-200"
                  />
                </div>
              )}
              <button
                type="button"
                onClick={() =>
                  setForm((p) => ({ ...p, cover_image: '', cover_image_thumb: '' }))
                }
                className="px-3 py-1.5 text-sm text-red-600 border border-red-300 rounded-lg hover:bg-red-50"
              >
                {t('admin.removeImages')}
              </button>
            </div>
          )}
          <div className="mt-2">
            <label className="block text-xs text-stone-500 mb-1">{t('admin.orPasteUrl')}</label>
            <input
              type="url"
              value={form.cover_image ?? ''}
              onChange={(e) =>
                setForm((p) => ({ ...p, cover_image: e.target.value, cover_image_thumb: e.target.value }))
              }
              placeholder={t('common.urlPlaceholder')}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500 text-sm"
            />
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-stone-700 mb-1">
              {t('admin.pages')}
            </label>
            <input
              type="number"
              min="1"
              value={form.pages ?? ''}
              onChange={(e) =>
                setForm((p) => ({
                  ...p,
                  pages: e.target.value ? parseInt(e.target.value, 10) : undefined,
                }))
              }
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-stone-700 mb-1">
              {t('admin.editionNumber')}
            </label>
            <input
              type="number"
              min="1"
              value={form.edition_number ?? ''}
              onChange={(e) =>
                setForm((p) => ({
                  ...p,
                  edition_number: e.target.value
                    ? parseInt(e.target.value, 10)
                    : undefined,
                }))
              }
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-stone-700 mb-1">
              {t('admin.publishYear')}
            </label>
            <input
              type="number"
              min="1000"
              max="2100"
              value={form.publish_year ?? ''}
              onChange={(e) =>
                setForm((p) => ({
                  ...p,
                  publish_year: e.target.value
                    ? parseInt(e.target.value, 10)
                    : undefined,
                }))
              }
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
            />
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">
            {t('admin.publisher')}
          </label>
          <input
            type="text"
            value={form.publisher || ''}
            onChange={(e) =>
              setForm((p) => ({ ...p, publisher: e.target.value }))
            }
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:ring-2 focus:ring-amber-500"
          />
        </div>
        {error && (
          <p className="text-red-600 text-sm">{error}</p>
        )}
        <div className="flex gap-3">
          <button
            type="submit"
            disabled={loading}
            className="px-6 py-2 bg-amber-900 text-amber-50 rounded-lg hover:bg-amber-800 disabled:opacity-50"
          >
            {loading ? t('common.saving') : isEdit ? t('admin.update') : t('admin.create')}
          </button>
          <button
            type="button"
            onClick={() => navigate('/admin/books')}
            className="px-6 py-2 border border-stone-300 rounded-lg hover:bg-stone-50"
          >
            {t('admin.cancel')}
          </button>
        </div>
      </form>
    </div>
  )
}
