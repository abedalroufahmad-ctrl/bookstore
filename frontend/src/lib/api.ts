import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_URL || '/api/v1'

export const api = axios.create({
  baseURL: API_BASE,
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (r) => r,
  (e) => {
    if (e.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('userType')
      window.location.href = '/login'
    }
    return Promise.reject(e)
  }
)

export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
}

export const books = {
  list: (params?: Record<string, string | number | boolean>) =>
    api.get<ApiResponse<{ data: Book[]; current_page: number }>>('/books', { params }),
  get: (id: string) => api.get<ApiResponse<Book>>(`/books/${id}`),
}

export const categories = {
  list: (params?: Record<string, string>) =>
    api.get<ApiResponse<{ data: Category[] }>>('/categories', { params }),
  get: (id: string) => api.get<ApiResponse<Category>>(`/categories/${id}`),
}

export const authors = {
  list: (params?: Record<string, string>) =>
    api.get<ApiResponse<{ data: Author[] }>>('/authors', { params }),
  get: (id: string) => api.get<ApiResponse<Author>>(`/authors/${id}`),
}

export const auth = {
  customerLogin: (email: string, password: string) =>
    api.post<ApiResponse<{ customer: Customer; token: string }>>('/customers/login', {
      email,
      password,
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

export const orders = {
  list: () => api.get<ApiResponse<{ data: Order[] }>>('/customers/orders'),
  checkout: (shippingAddress: ShippingAddress, paymentInfo?: object) =>
    api.post<ApiResponse<Order>>('/customers/orders/checkout', {
      shipping_address: shippingAddress,
      payment_info: paymentInfo,
    }),
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
  books: {
    list: (params?: Record<string, string | number>) =>
      api.get<ApiResponse<{ data: Book[] }>>('/admin/books', { params }),
    get: (id: string) => api.get<ApiResponse<Book>>(`/admin/books/${id}`),
    create: (data: BookFormData) => api.post<ApiResponse<Book>>('/admin/books', data),
    update: (id: string, data: Partial<BookFormData>) =>
      api.put<ApiResponse<Book>>(`/admin/books/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/books/${id}`),
  },
  warehouses: {
    list: (params?: Record<string, string>) =>
      api.get<ApiResponse<{ data: Warehouse[] }>>('/admin/warehouses', { params }),
  },
  orders: {
    list: (params?: Record<string, string | number | boolean>) =>
      api.get<ApiResponse<{ data: Order[] }>>('/admin/orders', { params }),
    get: (id: string) => api.get<ApiResponse<Order>>(`/admin/orders/${id}`),
    updateStatus: (id: string, status: string) =>
      api.patch<ApiResponse<Order>>(`/admin/orders/${id}/status`, { status }),
    assign: (id: string, employeeId: string) =>
      api.post<ApiResponse<Order>>(`/admin/orders/${id}/assign`, {
        employee_id: employeeId,
      }),
  },
  employees: {
    list: (params?: Record<string, string>) =>
      api.get<ApiResponse<{ data: Employee[] }>>('/admin/employees', { params }),
  },
  authors: {
    list: (params?: Record<string, string>) =>
      api.get<ApiResponse<{ data: Author[] }>>('/admin/authors', { params }),
    create: (data: { name: string; biography?: string }) =>
      api.post<ApiResponse<Author>>('/admin/authors', data),
    update: (id: string, data: { name?: string; biography?: string }) =>
      api.put<ApiResponse<Author>>(`/admin/authors/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/authors/${id}`),
  },
  categories: {
    list: (params?: Record<string, string>) =>
      api.get<ApiResponse<{ data: Category[] }>>('/admin/categories', { params }),
    create: (data: { dewey_code: string; subject_title: string; subject_number?: string }) =>
      api.post<ApiResponse<Category>>('/admin/categories', data),
    update: (id: string, data: { dewey_code?: string; subject_title?: string; subject_number?: string }) =>
      api.put<ApiResponse<Category>>(`/admin/categories/${id}`, data),
    delete: (id: string) => api.delete<ApiResponse<null>>(`/admin/categories/${id}`),
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
  publisher?: string
  size?: string
  weight?: number
  cover_image?: string
  cover_image_thumb?: string
  edition_number?: number
}

export interface Warehouse {
  _id: string
  name: string
  address?: string
}

export interface BookFormData {
  title: string
  author_ids: string[]
  category_id: string
  warehouse_id: string
  price: number
  isbn: string
  stock_quantity: number
  description?: string
  pages?: number
  publish_year?: number
  publisher?: string
  cover_image?: string
  cover_image_thumb?: string
  size?: string
  weight?: number
  edition_number?: number
}

export interface Category {
  _id: string
  dewey_code: string
  subject_title: string
}

export interface Author {
  _id: string
  name: string
  biography?: string
}

export interface Customer {
  _id: string
  name: string
  email: string
}

export interface Employee {
  _id: string
  name: string
  email: string
  role: string
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
  items: { book_id: string; quantity: number; price: number }[]
  shipping_address?: { address?: string; city?: string; country?: string; postal_code?: string }
  customer?: Customer
  employee?: Employee
  customer_id?: string
  employee_id?: string
  created_at?: string
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

export interface ShippingAddress {
  address: string
  city: string
  country: string
  postal_code?: string
}
