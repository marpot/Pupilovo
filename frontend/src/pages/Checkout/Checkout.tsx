import {
  useEffect,
  useState,
  type FormEvent,
} from 'react'
import { Link, useNavigate } from 'react-router-dom'

import {
  getCart,
  selectShippingRate,
  updateCartCustomer,
} from '@/api/cart'
import { getCurrentUser } from '@/api/auth'
import { emptyAddress, getCustomerAddresses, type CustomerAddress } from '@/api/addresses'
import AddressFields from '@/components/CustomerAddresses/AddressFields'
import { processCheckout } from '@/api/checkout'
import { formatPrice } from '@/pages/Cart/cartData'
import type { WooCommerceCart } from '@/types/woocommerce'

import '@/pages/Checkout/Checkout.scss'

function Checkout() {
  const navigate = useNavigate()

  const [billing, setBilling] = useState<CustomerAddress>(() => ({ ...emptyAddress(), email: '' }))
  const [shipping, setShipping] = useState<CustomerAddress>(emptyAddress)
  const [differentShipping, setDifferentShipping] = useState(false)
  const [note, setNote] = useState('')

  const [total, setTotal] = useState(0)
  const [shippingTotal, setShippingTotal] = useState(0)
  const [shippingName, setShippingName] =
    useState<string | null>(null)

  const [itemsCount, setItemsCount] = useState(0)
  const [expectedTotal, setExpectedTotal] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [isCalculatingShipping, setIsCalculatingShipping] =
    useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const [error, setError] = useState<string | null>(null)

  const updateSummary = (cart: WooCommerceCart) => {
    setItemsCount(cart.items_count)
    setExpectedTotal(cart.totals.total_price)
    setTotal(Number(cart.totals.total_price))
    setShippingTotal(
      Number(cart.totals.total_shipping ?? 0),
    )

    const selectedRate = cart.shipping_rates
      .flatMap((shippingPackage) =>
        shippingPackage.shipping_rates,
      )
      .find((rate) => rate.selected)

    setShippingName(selectedRate?.name ?? null)
  }

  const createAddresses = () => {
    const shippingAddress = { ...(differentShipping ? shipping : billing) }
    // Shipping has no native email field in WooCommerce.
    delete shippingAddress.email
    return { billingAddress: billing, shippingAddress }
  }

  const delivery = differentShipping ? shipping : billing
  const hasShippingAddress = ['first_name', 'last_name', 'address_1', 'city', 'country']
    .every((field) => Boolean(delivery[field as keyof CustomerAddress]?.trim()))

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

        if (controller.signal.aborted) return
        updateSummary(cart)
        // Recalculate rates for the addresses actually shown, including guest checkout.
        setShippingName(null)
        const session = await getCurrentUser()
        if (session.authenticated) {
          try {
            const addresses = await getCustomerAddresses(controller.signal)
            if (controller.signal.aborted) return
            setBilling({ ...addresses.billing, country: addresses.billing.country || 'PL', email: addresses.billing.email || session.user?.email || '' })
            setShipping({ ...addresses.shipping, country: addresses.shipping.country || 'PL' })
            setDifferentShipping(Boolean(addresses.shipping.address_1))
          } catch (addressError) {
            if (controller.signal.aborted) return
            setError(addressError instanceof Error ? addressError.message : 'Nie udało się pobrać adresów. Uzupełnij je ręcznie.')
          }
        }
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

  const handleCalculateShipping = async () => {
    if (
      !hasShippingAddress ||
      isCalculatingShipping
    ) {
      return
    }

    setError(null)
    setIsCalculatingShipping(true)

    const {
      billingAddress,
      shippingAddress,
    } = createAddresses()

    try {
      let cart = await updateCartCustomer(
        billingAddress,
        shippingAddress,
      )

      const shippingPackage = cart.shipping_rates[0]

      if (
        !shippingPackage ||
        !shippingPackage.shipping_rates.length
      ) {
        throw new Error(
          'Brak dostępnej metody dostawy dla podanego adresu.',
        )
      }

      const selectedRate =
        shippingPackage.shipping_rates.find(
          (rate) => rate.selected,
        )

      if (!selectedRate) {
        const firstRate =
          shippingPackage.shipping_rates[0]

        cart = await selectShippingRate(
          shippingPackage.package_id,
          firstRate.rate_id,
        )
      }

      updateSummary(cart)
    } catch (shippingError) {
      console.error(shippingError)

      setShippingName(null)
      setShippingTotal(0)

      setError(
        shippingError instanceof Error
          ? shippingError.message
          : 'Nie udało się obliczyć dostawy.',
      )
    } finally {
      setIsCalculatingShipping(false)
    }
  }

  const handleSubmit = async (
    event: FormEvent<HTMLFormElement>,
  ) => {
    event.preventDefault()

    if (
      isSubmitting ||
      isCalculatingShipping ||
      !shippingName ||
      !expectedTotal
    ) {
      return
    }

    setError(null)
    setIsSubmitting(true)

    const {
      billingAddress,
      shippingAddress,
    } = createAddresses()

    try {
      const checkout = await processCheckout({
        billing_address: billingAddress,
        shipping_address: shippingAddress,
        customer_note: note,
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
            <AddressFields type="billing" value={billing} disabled={isCalculatingShipping || isSubmitting}
              onChange={(value) => { setBilling(value); setShippingName(null) }} />
            <label className="checkout__different-address">
              <input type="checkbox" checked={differentShipping} disabled={isCalculatingShipping || isSubmitting}
                onChange={(event) => { setDifferentShipping(event.target.checked); setShippingName(null) }} />
              Dostawa na inny adres
            </label>
            {differentShipping && <AddressFields type="shipping" value={shipping} disabled={isCalculatingShipping || isSubmitting}
              onChange={(value) => { setShipping(value); setShippingName(null) }} />}
            <div className="checkout__fields">
              <label className="checkout__field--wide">
                Uwagi do zamówienia (opcjonalnie)
                <textarea value={note} onChange={(event) => setNote(event.target.value)} rows={4} />
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

                <dd>
                  {shippingName
                    ? `${shippingName} — ${formatPrice(
                        shippingTotal,
                      )}`
                    : 'Nieobliczona'}
                </dd>
              </div>

              <div className="checkout__total">
                <dt>Razem</dt>
                <dd>{formatPrice(total)}</dd>
              </div>
            </dl>

            {!shippingName && (
              <button
                type="button"
                disabled={
                  !hasShippingAddress ||
                  isCalculatingShipping
                }
                onClick={handleCalculateShipping}
              >
                {isCalculatingShipping
                  ? 'Obliczanie dostawy...'
                  : 'Oblicz dostawę'}
              </button>
            )}

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
              disabled={
                isSubmitting ||
                isCalculatingShipping ||
                !shippingName
              }
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