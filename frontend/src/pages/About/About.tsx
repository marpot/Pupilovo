import AboutHero from '@/components/AboutHero/AboutHero'
import AboutStory from '@/components/AboutStory/AboutStory'
import AboutValues from '@/components/AboutValues/AboutValues'
import AboutCta from '@/components/AboutCta/AboutCta'

function About() {
  return (
    <main id="about" className="about">
      <AboutHero />
      <AboutStory />
      <AboutValues />
      <AboutCta />
    </main>
  )
}

export default About
