import { useEffect, useRef } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'

import AboutHero from '@/components/AboutHero/AboutHero'
import AboutStory from '@/components/AboutStory/AboutStory'
import AboutValues from '@/components/AboutValues/AboutValues'
import AboutCta from '@/components/AboutCta/AboutCta'

function About() {
  const location = useLocation()
  const navigate = useNavigate()
  const aboutRef = useRef<HTMLElement>(null)
  const pathnameRef = useRef(location.pathname)

  useEffect(() => {
    pathnameRef.current = location.pathname
  }, [location.pathname])

  useEffect(() => {
    const aboutElement = aboutRef.current

    if (!aboutElement) {
      return
    }

    const updateActiveSection = () => {
      const isSectionRoute =
        pathnameRef.current === '/' || pathnameRef.current === '/shop'
      const shopElement = document.getElementById('shop')
      const activationOffset = window.innerHeight * 0.3
      const isAboutActive =
        aboutElement.getBoundingClientRect().top <= activationOffset &&
        (shopElement?.getBoundingClientRect().top ?? Infinity) >
          activationOffset

      if (isAboutActive && isSectionRoute) {
        navigate('/about', {
          replace: true,
          state: { aboutVisible: true },
        })
      }
    }

    window.addEventListener('scroll', updateActiveSection, { passive: true })

    return () => window.removeEventListener('scroll', updateActiveSection)
  }, [navigate])

  return (
    <main ref={aboutRef} id="about" className="about">
      <AboutHero />
      <AboutStory />
      <AboutValues />
      <AboutCta />
    </main>
  )
}

export default About
