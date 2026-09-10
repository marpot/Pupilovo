import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'

import CartItem from '@/components/CartItem/CartItem'
import CartSummary from '@/components/CartSummary/CartSummary'
import { formatPrice, previewProducts } from '@/pages/Cart/cartData'
import '@/pages/Cart/Cart.scss'

function CartContent({ preview }: { preview: boolean }) {
  const [products, setProducts] = useState(preview ? previewProducts : [])
  const [announcement, setAnnouncement] = useState('')
  const quantity = products.reduce((sum, product) => sum + product.quantity, 0)
  const subtotal = products.reduce((sum, product) => sum + product.quantity * product.price, 0)

  const handleQuantityChange = (id: string, nextQuantity: number) => {
    if (!Number.isInteger(nextQuantity) || nextQuantity < 1 || nextQuantity > 99) return

    setProducts((current) => current.map((product) => product.id === id ? { ...product, quantity: nextQuantity } : product))
    setAnnouncement('Zmieniono liczbę produktów.')
  }

  const handleRemove = (id: string) => {
    const product = products.find((item) => item.id === id)
    setProducts((current) => current.filter((item) => item.id !== id))
    setAnnouncement(`Usunięto: ${product?.name}.`)
  }

  return (
    <>
      {preview && <p className="cart__preview">Podgląd developerski — przykładowe produkty i zdjęcia. Zmiany nie są zapisywane.</p>}
      <p className="cart__status" role="status">{announcement && `${announcement} Suma produktów: ${formatPrice(subtotal)}.`}</p>
      {products.length ? (
        <div className="cart__layout">
          <section className="cart__products" aria-label="Produkty w koszyku">
            <div className="cart__list-heading"><h2>Twoje produkty</h2><span>{quantity} szt.</span></div>
            <ul className="cart__list">
              {products.map((product) => <CartItem key={product.id} product={product} onQuantityChange={handleQuantityChange} onRemove={handleRemove} />)}
            </ul>
            <Link className="cart__back" to="/shop#shop">← Kontynuuj zakupy</Link>
          </section>
          <CartSummary subtotal={subtotal} quantity={quantity} />
        </div>
      ) : (
        <section className="cart__empty" aria-labelledby="empty-cart-title">
          <span className="cart__icon" aria-hidden="true">♡</span>
          <h2 id="empty-cart-title">Twój koszyk czeka na coś dobrego</h2>
          <p>Nie ma tu jeszcze produktów. Znajdź coś dla swojego pupila.</p>
          <Link className="cart__shop-link" to="/shop#shop">Przejdź do sklepu</Link>
        </section>
      )}
      {import.meta.env.DEV && <Link className="cart__preview-link" to={preview ? '/cart' : '/cart?preview=filled'}>{preview ? 'Wróć do pustego koszyka' : 'Podgląd developerski: koszyk z produktami'}</Link>}
    </>
  )
}

function Cart() {
  const [searchParams] = useSearchParams()
  const preview = import.meta.env.DEV && searchParams.get('preview') === 'filled'

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' })
  }, [])

  return (
    <main className="cart">
      <div className="cart__container">
        <header className="cart__heading">
          <p className="cart__eyebrow">Dobry wybór dla Twojego pupila</p>
          <h1>Twój koszyk</h1>
          <p>Wszystko, czego potrzebuje. O krok bliżej domu.</p>
        </header>
        <CartContent key={String(preview)} preview={preview} />
      </div>
    </main>
  )
}

export default Cart
