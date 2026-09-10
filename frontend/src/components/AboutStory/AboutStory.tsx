import '@/components/AboutStory/AboutStory.scss'

function AboutStory() {
  return (
    <section className="about-story">
      <div className="about-story__image">
        <img
          src="/assets/man-with-dog.jpg"
          alt="Człowiek z psem"
        />
      </div>

      <div className="about-story__content">
        <p className="about-story__eyebrow">Nasza historia</p>

        <h2>
          Tworzymy Pupilovo z myślą o codziennym życiu ze zwierzętami.
        </h2>

        <p>
          Wierzymy, że wybieranie produktów dla naszych pupili nie powinno
          oznaczać przeszukiwania setek przypadkowych ofert. Dlatego chcemy
          tworzyć miejsce, w którym łatwo znaleźć rzeczy praktyczne,
          przemyślane i naprawdę przydatne.
        </p>

        <p>
          Pupilovo to połączenie miłości do zwierząt, prostoty i świadomego
          wyboru. Każdy produkt ma mieć swoje miejsce i konkretny powód,
          dla którego trafia do naszej oferty.
        </p>
      </div>
    </section>
  )
}

export default AboutStory