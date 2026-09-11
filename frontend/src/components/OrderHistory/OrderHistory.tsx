import { useEffect, useState } from 'react'

import { getCustomerOrders } from '@/api/orders'
import type { OrderPage } from '@/api/orders'
import '@/components/OrderHistory/OrderHistory.scss'

type LoadState =
  | { kind: 'loading' }
  | { kind: 'error'; message: string }
  | { kind: 'ready'; data: OrderPage }

function OrderPageContent({ page, onPageChange }: { page: number; onPageChange: (page: number) => void }) {
  const [state, setState] = useState<LoadState>({ kind: 'loading' })
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    void getCustomerOrders(page, controller.signal).then((data) => {
      if (!controller.signal.aborted) setState({ kind: 'ready', data })
    }).catch((error: unknown) => {
      if (!controller.signal.aborted) {
        setState({ kind: 'error', message: error instanceof Error ? error.message : 'Nie udało się pobrać zamówień.' })
      }
    })
    return () => controller.abort()
  }, [page, attempt])

  if (state.kind === 'loading') return <p role="status">Ładowanie zamówień…</p>
  if (state.kind === 'error') return (
    <div>
      <p role="alert">{state.message}</p>
      <button type="button" onClick={() => { setState({ kind: 'loading' }); setAttempt(attempt + 1) }}>Spróbuj ponownie</button>
    </div>
  )

  const { orders, totalPages } = state.data
  return (
    <div className="order-history">
      {orders.length === 0 && <p>{page === 1 ? 'Nie masz jeszcze żadnych zamówień.' : 'Brak zamówień na tej stronie.'}</p>}
      <ul className="order-history__list">
        {orders.map((order) => (
          <li key={order.id} className="order-history__order">
            <div className="order-history__heading">
              <h3>Zamówienie #{order.number}</h3>
              <span className="order-history__status">{order.statusLabel}</span>
            </div>
            <p className="order-history__summary">
              {order.createdAt && <time dateTime={order.createdAt}>{new Intl.DateTimeFormat('pl-PL', { dateStyle: 'long' }).format(new Date(order.createdAt))}</time>}
              <strong>{new Intl.NumberFormat('pl-PL', { style: 'currency', currency: order.currency }).format(Number(order.total))}</strong>
            </p>
            <details>
              <summary>Produkty w zamówieniu ({order.items.reduce((sum, item) => sum + item.quantity, 0)})</summary>
              <ul>
                {order.items.map((item) => <li key={item.id}>{item.name} <span>× {item.quantity}</span></li>)}
              </ul>
            </details>
          </li>
        ))}
      </ul>
      {(totalPages > 1 || page > 1) && (
        <nav className="order-history__pagination" aria-label="Strony historii zamówień">
          <button type="button" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>Poprzednia</button>
          <span>Strona {page} z {Math.max(page, totalPages)}</span>
          <button type="button" disabled={page >= totalPages} onClick={() => onPageChange(page + 1)}>Następna</button>
        </nav>
      )}
    </div>
  )
}

export default function OrderHistory() {
  const [page, setPage] = useState(1)
  return <OrderPageContent key={page} page={page} onPageChange={setPage} />
}
