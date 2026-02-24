import { useQuery } from '@tanstack/react-query'
import { admin } from '../lib/api'

export function AdminEmployees() {
  const { data, isLoading } = useQuery({
    queryKey: ['admin-employees'],
    queryFn: async () => {
      const res = await admin.employees.list()
      return res.data
    },
  })

  if (isLoading) return <div className="text-center py-12">Loading...</div>

  const items = data?.data?.data ?? data?.data ?? []

  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-6">Employees</h1>
      <div className="bg-white rounded-lg border border-stone-200 overflow-hidden">
        <table className="w-full">
          <thead className="bg-stone-100">
            <tr>
              <th className="px-4 py-2 text-left">Name</th>
              <th className="px-4 py-2 text-left">Email</th>
              <th className="px-4 py-2 text-left">Role</th>
            </tr>
          </thead>
          <tbody>
            {items.map((emp: { _id: string; name: string; email: string; role: string }) => (
              <tr key={emp._id} className="border-t border-stone-200">
                <td className="px-4 py-2">{emp.name}</td>
                <td className="px-4 py-2">{emp.email}</td>
                <td className="px-4 py-2">{emp.role}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 && (
        <p className="text-center text-stone-500 py-8">No employees</p>
      )}
    </div>
  )
}
