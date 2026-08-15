import axios from 'axios'
import i18n from '../i18n'

const API_BASE = import.meta.env.VITE_API_URL || '/api/v1'

export const api = axios.create({
  baseURL: API_BASE,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  const lang = (i18n.language || localStorage.getItem('lang') || 'ar').startsWith('ar')
    ? 'ar'
    : 'en'
  config.headers['Accept-Language'] = lang
  config.headers['X-Locale'] = lang
  return config
})

const AUTH_ATTEMPT_PATH = /^\/(customers|employees)\/(login|register)$/

api.interceptors.response.use(
  (r) => r,
  (e) => {
    if (e.response?.status === 401) {
      const requestPath = (e.config?.url ?? '').replace(/\?.*$/, '')
      const isAuthAttempt = AUTH_ATTEMPT_PATH.test(requestPath)
      const hadToken = Boolean(localStorage.getItem('token'))

      // Wrong login credentials also return 401 — show the form error, don't reload the page.
      if (!isAuthAttempt && hadToken) {
        localStorage.removeItem('token')
        localStorage.removeItem('userType')
        window.location.href = '/login'
      }
    }
    return Promise.reject(e)
  }
)

export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  /** Public catalog: max offset page the API will serve. */
  max_page?: number
  /** Optional seek token for the next page (avoids deep SKIP). */
  next_cursor?: string
}

export const books = {
  list: (params?: Record<string, string | number | boolean>) =>
    api.get<ApiResponse<PaginatedResponse<Book>>>('/books', { params }),
  get: (id: string) => api.get<ApiResponse<Book>>(`/books/${id}`),
}

export const categories = {
  list: (params?: Record<string, string | number>) =>
    api.get<ApiResponse<PaginatedResponse<Category>>>('/categories', { params }),
  get: (id: string) => api.get<ApiResponse<Category>>(`/categories/${id}`),
}

export const warehousesPublic = {
  list: (params?: Record<string, string | number>) =>
    api.get<ApiResponse<PaginatedResponse<Warehouse>>>('/warehouses', { params }),
  get: (id: string) => api.get<ApiResponse<Warehouse>>(`/warehouses/${id}`),
}

export const publishersPublic = {
  get: (id: string) => api.get<ApiResponse<Publisher>>(`/publishers/${id}`),
}

export const authors = {
  list: (params?: Record<string, string | number>) =>
    api.get<ApiResponse<PaginatedResponse<Author>>>('/authors', { params }),
  get: (id: string) => api.get<ApiResponse<Author>>(`/authors/${id}`),
}

/** Public settings (no auth) - for global_discount etc. */
export const settings = {
  get: () => api.get<ApiResponse<Record<string, unknown>>>('/settings'),
}

export const auth = {
  customerLogin: (email: string, password: string, rememberMe = false) =>
    api.post<ApiResponse<{ customer: Customer; token: string }>>('/customers/login', {
      email,
      password,
      remember_me: rememberMe,
    }),
  customerRegister: (data: RegisterData) =>
    api.post<ApiResponse<{ customer: Customer; token: string }>>('/customers/register', data),
  employeeLogin: (email: string, password: string) =>
    api.post<ApiResponse<{ employee: Employee; token: string }>>('/employees/login', {
      email,
      password,
    }),
  customerLogout: () => api.post<ApiResponse<null>>('/customers/logout'),
  employeeLogout: () => api.post<ApiResponse<null>>('/employees/logout'),
  customerMe: () => api.get<ApiResponse<Customer>>('/customers/me'),
  updateProfile: (data: CustomerProfileUpdateData) =>
    api.put<ApiResponse<Customer>>('/customers/profile', data),
  employeeMe: () => api.get<ApiResponse<Employee>>('/employees/me'),
}

export const cart = {
  get: () => api.get<ApiResponse<{ cart: Cart; items: CartItemDetail[]; total: number }>>('/customers/cart'),
  addItem: (bookId: string, quantity: number) =>
    api.post<ApiResponse<{ cart: Cart; total: number }>>('/customers/cart/items', {
      book_id: bookId,
      quantity,
    }),
  removeItem: (bookId: string) =>
    api.delete<ApiResponse<{ cart: Cart; total: number }>>(`/customers/cart/items/${bookId}`),
  updateItem: (bookId: string, quantity: number) =>
    api.patch<ApiResponse<{ cart: Cart; total: number }>>(`/customers/cart/items/${bookId}`, {
      quantity,
    }),
}

export type PaymentMethodId = string

export const orders = {
  list: (params?: Record<string, string | number>) =>
    api.get<ApiResponse<PaginatedResponse<Order>>>('/customers/orders', { params }),
  get: (id: string) => api.get<ApiResponse<Order>>(`/customers/orders/${id}`),
  checkout: (
    shippingAddress: ShippingAddress,
    paymentMethod: PaymentMethodId,
    paymentInfo?: object
  ) =>
    api.post<ApiResponse<{ orders: Order[]; count: number }>>('/customers/orders/checkout', {
      shipping_address: shippingAddress,
      payment_method: paymentMethod,
      payment_info: paymentInfo,
    }),
  confirmQuote: (id: string) =>
    api.post<ApiResponse<Order>>(`/customers/orders/${id}/confirm-quote`),
  paypalStartQuoted: (orderIds: string[]) =>
    api.post<
      ApiResponse<{
        orders: Order[]
        count: number
        paypal_order_id: string
        approval_url: string
      }>
    >('/customers/orders/paypal/start', { order_ids: orderIds }),
}

export const admin = {
  uploadCover: (file: File) => {
    const formData = new FormData()
    formData.append('cover_image', file)
    return api.post<ApiResponse<{ cover_image: string; cover_image_thumb: string }>>(
      '/admin/upload-cover',
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
  },
  uploadAuthorPhoto: (file: File) => {
    const formData = new FormData()
    formData.append('photo', file)
    return api.post<ApiResponse<{ photo: string }>>(
      '/admin/upload-author-photo',
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
  },
  books: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<PaginatedResponse<Book>>>('/admin/books', { params }),
    get: (id: string) => api.get<ApiResponse<Book>>(`/admin/books/${id}`),
    create: (data: BookFormData) => api.post<ApiResponse<Book>>('/admin/books', data),
    update: (id: string, data: Partial<BookFormData>) =>
      api.put<ApiResponse<Book>>(`/admin/books/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/books/${id}`),
    import: (file: File, warehouseId: string, skipCover = true) => {
      const form = new FormData()
      form.append('file', file)
      form.append('warehouse_id', warehouseId)
      form.append('skip_cover', skipCover ? '1' : '0')
      return api.post<
        ApiResponse<{ created: number; skipped: number; errors: number; messages: string[] }>
      >('/admin/books/import', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
  },
  warehouses: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<PaginatedResponse<Warehouse>>>('/admin/warehouses', { params }),
    get: (id: string) => api.get<ApiResponse<Warehouse>>(`/admin/warehouses/${id}`),
    create: (data: WarehouseFormData) =>
      api.post<ApiResponse<Warehouse>>('/admin/warehouses', data),
    update: (id: string, data: Partial<WarehouseFormData>) =>
      api.put<ApiResponse<Warehouse>>(`/admin/warehouses/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/warehouses/${id}`),
  },
  orders: {
    list: (params?: Record<string, string | number | boolean>) =>
      api.get<ApiResponse<PaginatedResponse<Order>>>('/admin/orders', { params }),
    get: (id: string) => api.get<ApiResponse<Order>>(`/admin/orders/${id}`),
    updateStatus: (id: string, status: string) =>
      api.patch<ApiResponse<Order>>(`/admin/orders/${id}/status`, { status }),
    assign: (id: string, employeeId: string) =>
      api.post<ApiResponse<Order>>(`/admin/orders/${id}/assign`, {
        employee_id: employeeId,
      }),
    submitWarehouseQuote: (
      id: string,
      body: { shipping_fee: number; shipping_method?: string; payment_method?: string },
    ) => api.post<ApiResponse<Order>>(`/admin/orders/${id}/warehouse-quote`, body),
  },
  employees: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<PaginatedResponse<Employee>>>('/admin/employees', { params }),
    create: (data: {
      name: string
      email: string
      password: string
      password_confirmation: string
      role: string
      warehouse_id?: string
      warehouse_ids?: string[]
      publisher_id?: string
    }) => api.post<ApiResponse<Employee>>('/admin/employees', data),
    update: (id: string, data: {
      name?: string
      email?: string
      password?: string
      password_confirmation?: string
      role?: string
      warehouse_id?: string
      warehouse_ids?: string[]
      publisher_id?: string
    }) => api.put<ApiResponse<Employee>>(`/admin/employees/${id}`, data),
  },
  customers: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<PaginatedResponse<Customer>>>('/admin/customers', { params }),
    get: (id: string) => api.get<ApiResponse<Customer>>(`/admin/customers/${id}`),
    update: (id: string, data: {
      name?: string
      email?: string
      phone?: string
      address?: string
      city?: string
      country?: string
      postal_code?: string
      password?: string
      password_confirmation?: string
    }) => api.put<ApiResponse<Customer>>(`/admin/customers/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/customers/${id}`),
    convertToEmployee: (id: string, data: {
      role: string
      warehouse_id: string
      password?: string
      password_confirmation?: string
    }) => api.post<ApiResponse<Employee>>(`/admin/customers/${id}/convert-to-employee`, data),
  },
  authors: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<PaginatedResponse<Author>>>('/admin/authors', { params }),
    get: (id: string) => api.get<ApiResponse<Author>>(`/admin/authors/${id}`),
    create: (data: { name: string; biography?: string; date_of_birth?: string; date_of_death?: string; photo?: string }) =>
      api.post<ApiResponse<Author>>('/admin/authors', data),
    update: (id: string, data: { name?: string; biography?: string; date_of_birth?: string; date_of_death?: string; photo?: string }) =>
      api.put<ApiResponse<Author>>(`/admin/authors/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/authors/${id}`),
  },
  categories: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<PaginatedResponse<Category>>>('/admin/categories', { params }),
    create: (data: { dewey_code: string; subject_title_en: string; subject_title_ar?: string; subject_number?: string }) =>
      api.post<ApiResponse<Category>>('/admin/categories', data),
    update: (id: string, data: { dewey_code?: string; subject_title_en?: string; subject_title_ar?: string; subject_number?: string }) =>
      api.put<ApiResponse<Category>>(`/admin/categories/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/categories/${id}`),
  },
  publishers: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<PaginatedResponse<Publisher>>>('/admin/publishers', { params }),
    get: (id: string) => api.get<ApiResponse<Publisher>>(`/admin/publishers/${id}`),
    create: (data: { name: string; address?: string; phone?: string; email?: string; website?: string }) =>
      api.post<ApiResponse<Publisher>>('/admin/publishers', data),
    update: (id: string, data: { name?: string; address?: string; phone?: string; email?: string; website?: string }) =>
      api.put<ApiResponse<Publisher>>(`/admin/publishers/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/publishers/${id}`),
  },
  settings: {
    get: () => api.get<ApiResponse<Record<string, any>>>('/admin/settings'),
    update: (data: Record<string, any>) => api.put<ApiResponse<null>>('/admin/settings', data),
  },
  countries: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<PaginatedResponse<Country>>>('/admin/countries', { params }),
    get: (id: string) => api.get<ApiResponse<Country>>(`/admin/countries/${id}`),
    create: (data: CountryFormData) => api.post<ApiResponse<Country>>('/admin/countries', data),
    update: (id: string, data: CountryFormData) =>
      api.put<ApiResponse<Country>>(`/admin/countries/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/countries/${id}`),
    syncFromNetwork: (dryRun?: boolean) =>
      api.post<ApiResponse<{ message: string; output: string }>>('/admin/countries/sync-from-network', null, {
        params: dryRun ? { dry_run: 1 } : undefined,
      }),
    syncCitiesFromDataset: (opts?: { dry_run?: boolean; limit?: number }) =>
      api.post<ApiResponse<{ message: string; output: string }>>(
        '/admin/countries/sync-cities-from-dataset',
        null,
        { params: opts },
      ),
  },
}

export interface Book {
  _id: string
  title: string
  price: number
  stock_quantity: number
  isbn?: string
  author_ids?: string[]
  category_id?: string
  category?: Category
  authors?: Author[]
  warehouse_id?: string
  warehouse?: Warehouse
  description?: string
  pages?: number
  publish_year?: number
  publisher_id?: string
  publisher?: Publisher
  size?: string
  weight?: number
  cover_image?: string
  cover_image_thumb?: string
  edition_number?: number
  discount_percent?: number
  binding_type?: string
  paper_type?: string
  condition?: 'new' | 'used'
  is_visible?: boolean
  is_sold?: boolean
}

export interface Warehouse {
  _id: string
  name: string
  address?: string
  country?: string
  city?: string
  phone?: string
  email?: string
  publisher_id?: string
  publisher?: Publisher
  manager_id?: string | null
  manager?: Employee | null
  employees?: Employee[]
}

export interface WarehouseFormData {
  name: string
  address: string
  country: string
  city: string
  phone?: string
  email: string
  publisher_id: string
  manager_id?: string | null
  employee_ids?: string[]
}

export interface CountryCity {
  id?: string
  name: string
}

export interface Country {
  _id: string
  name: string
  code?: string
  currency_code: string
  currency_name?: string
  cities?: CountryCity[]
}

export interface CountryFormData {
  name: string
  code: string
  currency_code: string
  currency_name: string
  cities: CountryCity[]
}

export interface BookFormData {
  title: string
  author_ids: string[]
  category_id: string
  warehouse_id: string
  warehouse_ids?: string[]
  price: number
  isbn: string
  stock_quantity: number
  description?: string
  pages?: number
  publish_year?: number
  publisher_id?: string
  cover_image?: string
  cover_image_thumb?: string
  size?: string
  weight?: number
  edition_number?: number
  discount_percent?: number
  condition?: 'new' | 'used'
  is_visible?: boolean
  is_sold?: boolean
}

export interface Category {
  _id: string
  dewey_code: string
  subject_title_en: string
  subject_title_ar?: string
  books_count?: number
}

export interface Publisher {
  _id: string
  name: string
  address?: string
  phone?: string
  email?: string
  website?: string
  warehouses_count?: number
  warehouses?: Warehouse[]
}

export interface Author {
  _id: string
  name: string
  biography?: string
  date_of_birth?: string
  date_of_death?: string
  photo?: string
  books_count?: number
  books?: Book[]
}

export interface Customer {
  _id: string
  name: string
  email: string
  address?: string
  city?: string
  country?: string
  postal_code?: string
  phone?: string
}

export interface Employee {
  _id: string
  name: string
  email: string
  role: string
  warehouse_id?: string
  warehouse_ids?: string[]
  publisher_id?: string
}

export interface Cart {
  _id: string
  items: { book_id: string; quantity: number; price: number }[]
}

export interface CartItemDetail {
  book_id: string
  quantity: number
  price: number
  subtotal: number
  book?: { id: string; title: string; price: number }
}

export interface Order {
  _id: string
  status: string
  total: number
  books_subtotal?: number
  shipping_fee?: number
  shipping_method?: string | null
  items: { book_id: string; quantity: number; price: number; book_title?: string }[]
  shipping_address?: { address?: string; city?: string; country?: string; postal_code?: string }
  customer?: Customer
  employee?: Employee
  customer_id?: string
  employee_id?: string
  warehouse_id?: string
  created_at?: string
  payment_method?: string
  payment_status?: string
}

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
  address?: string
  city?: string
  country?: string
}

export interface CustomerProfileUpdateData {
  name?: string
  email?: string
  password?: string
  password_confirmation?: string
  address?: string | null
  city?: string | null
  country?: string | null
  postal_code?: string | null
  phone?: string | null
}

export interface ShippingAddress {
  address: string
  city: string
  country: string
  postal_code?: string
}
