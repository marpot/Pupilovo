import '@/components/AboutValues/AboutValues.scss'

const values = [
  {
    title: 'Komfort',
    icon: '✦',
    description:
      'Wybieramy produkty, które pomagają zadbać o codzienny komfort i dobre samopoczucie zwierząt.',
  },
  {
    title: 'Bliskość',
    icon: '♡',
    description:
      'Zwierzęta są częścią naszych rodzin. Dlatego patrzymy na ich potrzeby z troską i empatią.',
  },
  {
    title: 'Prostota',
    icon: '✓',
    description:
      'Stawiamy na rzeczy praktyczne i przemyślane, bez zbędnych produktów i komplikowania prostych wyborów.',
  },
]

function AboutValues() {
  return (
    <section className="about-values">
      <div className="about-values__header">
        <p className="about-values__eyebrow">Nasze wartości</p>

        <h2>To, co jest dla nas ważne.</h2>
      </div>

      <div className="about-values__list">
        {values.map((value) => (
          <article className="about-values__item" key={value.title}>
            <span className="about-values__icon" aria-hidden="true">
              {value.icon}
            </span>

            <h3>{value.title}</h3>
            <p>{value.description}</p>
          </article>
        ))}
      </div>
    </section>
  )
}

export default AboutValues