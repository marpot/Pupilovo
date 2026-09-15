import { Link } from 'react-router-dom'
import '@/components/Footer/Footer.scss'
export default function Footer() { return <footer className="footer"><div className="footer__container"><span>© Pupilovo · sklep demonstracyjny</span><nav aria-label="Informacje"><Link to="/contact">Kontakt</Link><Link to="/delivery">Dostawa i płatności</Link><Link to="/returns">Zwroty i reklamacje</Link><Link to="/faq">FAQ</Link><Link to="/terms">Regulamin</Link><Link to="/privacy">Polityka prywatności</Link></nav></div></footer> }
