import '@/components/ScrollArrow/ScrollArrow.scss'

type ScrollArrowProps = {
  targetId: string
}

function ScrollArrow({ targetId }: ScrollArrowProps) {
  const handleScroll = () => {
    document.getElementById(targetId)?.scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    })
  }

  return (
    <button
      className="scroll-arrow"
      onClick={handleScroll}
      aria-label="Przewiń w dół"
      type="button"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M12 5v14" />
        <path d="m19 12-7 7-7-7" />
      </svg>
    </button>
  )
}

export default ScrollArrow