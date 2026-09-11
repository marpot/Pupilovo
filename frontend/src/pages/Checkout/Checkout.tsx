import {
  useEffect,
  useState,
  type FormEvent,
} from 'react'
import { Link, useNavigate } from 'react-router-dom'

import { getCart } from '@/api/cart'
import { processCheckout } from '@/api/checkout'
import { formatPrice } from '@/pages/Cart/cartData'

import '@/pages/Checkout/Checkout.scss'

type CheckoutForm = {
  firstName: string
  lastName: string
  email: string
  phone: string
  address: string
  postcode: string
  city: string
  note: string
}

const initialForm: CheckoutForm = {
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  address: '',
  postcode: '',
  city: '',
  note: '',
}

function Checkout() {
  const navigate = useNavigate()

  const [form, setForm] =
    useState<CheckoutForm>(initialForm)

  const [total, setTotal] = useState(0)
  const [itemsCount, setItemsCount] = useState(0)
  const [expectedTotal, setExpectedTotal] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    window.scrollTo({
      top: 0,
      behavior: 'instant',
    })

    const controller = new AbortController()

    const loadCart = async () => {
      try {
        setIsLoading(true)

        const cart = await getCart(controller.signal)

        setItemsCount(cart.items_count)
        setExpectedTotal(cart.totals.total_price)
        setTotal(Number(cart.totals.total_price))
      } catch (cartError) {
        if (
          cartError instanceof DOMException &&
          cartError.name === 'AbortError'
        ) {
          return
        }

        console.error(cartError)

        setError(
          'Nie udało się pobrać koszyka.',
        )
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false)
        }
      }
    }

    void loadCart()

    return () => controller.abort()
  }, [])

  const handleChange = (
    field: keyof CheckoutForm,
    value: string,
  ) => {
    setForm((current) => ({
      ...current,
      [field]: value,
    }))
  }

  const handleSubmit = async (
    event: FormEvent<HTMLFormElement>,
  ) => {
    event.preventDefault()

    if (isSubmitting) {
      return
    }

    setError(null)
    setIsSubmitting(true)

    const billingAddress = {
      first_name: form.firstName,
      last_name: form.lastName,
      company: '',
      address_1: form.address,
      address_2: '',
      city: form.city,
      state: '',
      postcode: form.postcode,
      country: 'PL',
      email: form.email,
      phone: form.phone,
    }

    const shippingAddress = {
      first_name: form.firstName,
      last_name: form.lastName,
      company: '',
      address_1: form.address,
      address_2: '',
      city: form.city,
      state: '',
      postcode: form.postcode,
      country: 'PL',
    }

    try {
      const checkout = await processCheckout({
        billing_address: billingAddress,
        shipping_address: shippingAddress,
        customer_note: form.note,
        payment_method: 'cod',
        payment_data: [],
        expected_total: expectedTotal,
      })

      navigate(
        `/order-confirmation/${checkout.order_id}`,
        {
          state: {
            orderId: checkout.order_id,
            orderNumber:
              checkout.order_number ??
              String(checkout.order_id),
            status: checkout.status,
          },
        },
      )
    } catch (checkoutError) {
      console.error(checkoutError)

      setError(
        checkoutError instanceof Error
          ? checkoutError.message
          : 'Nie udało się złożyć zamówienia.',
      )
    } finally {
      setIsSubmitting(false)
    }
  }

  if (isLoading) {
    return (
      <main className="checkout">
        <div className="checkout__container">
          <p role="status">
            Ładowanie zamówienia...
          </p>
        </div>
      </main>
    )
  }

  if (!itemsCount) {
    return (
      <main className="checkout">
        <div className="checkout__container">
          <h1>Koszyk jest pusty</h1>

          <Link to="/shop#shop">
            Wróć do sklepu
          </Link>
        </div>
      </main>
    )
  }

  return (
    <main className="checkout">
      <div className="checkout__container">
        <header className="checkout__heading">
          <p className="checkout__eyebrow">
            Finalizacja zamówienia
          </p>

          <h1>Twoje dane</h1>

          <p>
            Uzupełnij dane potrzebne do realizacji
            zamówienia.
          </p>
        </header>

        <form
          className="checkout__layout"
          onSubmit={handleSubmit}
        >
          <section className="checkout__form">
            <div className="checkout__fields">
              <label>
                Imię

                <input
                  required
                  autoComplete="given-name"
                  value={form.firstName}
                  onChange={(event) =>
                    handleChange(
                      'firstName',
                      event.target.value,
                    )
                  }
                />
              </label>

              <label>
                Nazwisko

                <input
                  required
                  autoComplete="family-name"
                  value={form.lastName}
                  onChange={(event) =>
                    handleChange(
                      'lastName',
                      event.target.value,
                    )
                  }
                />
              </label>

              <label>
                E-mail

                <input
                  required
                  type="email"
                  autoComplete="email"
                  value={form.email}
                  onChange={(event) =>
                    handleChange(
                      'email',
                      event.target.value,
                    )
                  }
                />
              </label>

              <label>
                Telefon

                <input
                  required
                  type="tel"
                  autoComplete="tel"
                  value={form.phone}
                  onChange={(event) =>
                    handleChange(
                      'phone',
                      event.target.value,
                    )
                  }
                />
              </label>

              <label className="checkout__field--wide">
                Adres

                <input
                  required
                  autoComplete="street-address"
                  value={form.address}
                  onChange={(event) =>
                    handleChange(
                      'address',
                      event.target.value,
                    )
                  }
                />
              </label>

              <label>
                Kod pocztowy

                <input
                  required
                  autoComplete="postal-code"
                  placeholder="00-000"
                  value={form.postcode}
                  onChange={(event) =>
                    handleChange(
                      'postcode',
                      event.target.value,
                    )
                  }
                />
              </label>

              <label>
                Miasto

                <input
                  required
                  autoComplete="address-level2"
                  value={form.city}
                  onChange={(event) =>
                    handleChange(
                      'city',
                      event.target.value,
                    )
                  }
                />
              </label>

              <label className="checkout__field--wide">
                Uwagi do zamówienia

                <textarea
                  rows={4}
                  value={form.note}
                  onChange={(event) =>
                    handleChange(
                      'note',
                      event.target.value,
                    )
                  }
                />
              </label>
            </div>
          </section>

          <aside className="checkout__summary">
            <h2>Podsumowanie</h2>

            <dl>
              <div>
                <dt>Produkty</dt>
                <dd>{itemsCount} szt.</dd>
              </div>

              <div>
                <dt>Dostawa</dt>
                <dd>Do ustalenia</dd>
              </div>

              <div className="checkout__total">
                <dt>Razem</dt>
                <dd>{formatPrice(total)}</dd>
              </div>
            </dl>

            <p className="checkout__payment">
              Płatność testowa: przy odbiorze
            </p>

            {error && (
              <p
                className="checkout__error"
                role="alert"
              >
                {error}
              </p>
            )}

            <button
              type="submit"
              disabled={isSubmitting}
            >
              {isSubmitting
                ? 'Składanie zamówienia...'
                : 'Złóż zamówienie'}
            </button>

            <Link
              to="/cart"
              className="checkout__back"
            >
              ← Wróć do koszyka
            </Link>
          </aside>
        </form>
      </div>
    </main>
  )
}

export default Checkout